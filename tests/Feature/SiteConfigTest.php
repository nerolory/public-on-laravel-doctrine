<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Role;
use App\Enums\Settings\SettingGroup;
use App\Services\Analytics\Providers\Ga4Provider;
use Closure;
use Google\Analytics\Data\V1beta\DimensionValue;
use Google\Analytics\Data\V1beta\MetricValue;
use Google\Analytics\Data\V1beta\Row;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Illuminate\Http\Client\Factory as Http;
use Mockery;
use Tests\DoctrineTestCase;

/**
 * Публичная конфигурация сайта: счётчики, ссылки «поделиться», причины жалоб и отчёты аналитики.
 */
final class SiteConfigTest extends DoctrineTestCase
{
    /**
     * В конфигурации только включённые счётчики с заполненным идентификатором и без секретов.
     */
    public function test_site_config_exposes_only_enabled_counters_without_secrets(): void
    {
        $this->updateSettings(SettingGroup::Metrics, [
            'metrics__yandex_enabled' => '1', 'metrics__yandex_id' => '12345678',
            'metrics__yandex_token' => 'secret-token',
            'metrics__meta_enabled' => '0', 'metrics__meta_id' => '1234567890',
        ]);

        $config = $this->getJson('/api/site')->assertOk()->json();
        self::assertSame(
            [[
                'type' => 'yandex', 'id' => '12345678', 'consent' => 'analytics',
                'script' => 'https://mc.yandex.ru/metrika/tag.js',
            ]],
            $config['counters'],
        );
        self::assertStringNotContainsString('secret-token', json_encode($config));
    }

    /**
     * Счётчик Google Analytics 4 попадает в конфигурацию с согласием на аналитику и своим скриптом.
     */
    public function test_ga4_counter_is_exposed_with_analytics_consent(): void
    {
        $this->updateSettings(
            SettingGroup::Metrics,
            ['metrics__ga4_enabled' => '1', 'metrics__ga4_id' => 'G-TEST1234'],
        );

        self::assertSame(
            [[
                'type' => 'ga4',
                'id' => 'G-TEST1234',
                'consent' => 'analytics',
                'script' => 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234',
            ]],
            $this->getJson('/api/site')->assertOk()->json('counters'),
        );
    }

    /**
     * Отчёт GA4 запрашивается по ресурсу из настроек и выводится в админке.
     */
    public function test_ga4_report_is_read_through_data_api(): void
    {
        // Клиент Data API финальный, поэтому подменяется объект без класса: провайдер вызывает только runReport().
        $client = Mockery::mock();
        $client->shouldReceive('runReport')
            ->once()
            ->withArgs(static fn (RunReportRequest $request): bool => $request->getProperty() === 'properties/555')
            ->andReturn(new RunReportResponse(['rows' => [
                $this->ga4Row('20261030', [5, 4, 9]),
                $this->ga4Row('20261029', [10, 8, 25]),
            ]]));
        $this->app->when(Ga4Provider::class)->needs(Closure::class)
            ->give(static fn (): Closure => static fn (array $credentials) => $client);
        $this->updateSettings(SettingGroup::Metrics, [
            'metrics__ga4_enabled' => '1',
            'metrics__ga4_property' => '555',
            'metrics__ga4_credentials' => '{"type":"service_account"}',
        ]);

        $this->actingAsRole(Role::ADMINISTRATOR);
        $this->get('/admin/page/analytics')->assertOk()->assertSee('Всего: визитов 15, посетителей 12, просмотров 34.');
    }

    /**
     * Ссылки «поделиться» включаются настройками и содержат заголовок статьи в кодировке адреса.
     */
    public function test_share_links_follow_settings(): void
    {
        $article = $this->createArticle($this->createUser('author@example.test'), 'Поделиться & проверить', '<p>Т</p>');

        $share = $this->getJson('/api/articles/' . $article->getId())->assertOk()->json('share');
        self::assertSame(['vk', 'ok', 'telegram'], array_column($share, 'network'));
        self::assertStringContainsString(
            'title=%D0%9F%D0%BE%D0%B4%D0%B5%D0%BB%D0%B8%D1%82%D1%8C%D1%81%D1%8F%20%26%20',
            $share[0]['url'],
        );
    }

    /**
     * Отчёт Яндекс Метрики читается через API и выводится в админке.
     */
    public function test_yandex_report_is_read_through_api(): void
    {
        $http = $this->app->make(Http::class);
        $http->fake(['api-metrika.yandex.net/*' => $http->response(['data' => [
            ['dimensions' => [['name' => '2026-10-29']], 'metrics' => [10, 8, 25]],
            ['dimensions' => [['name' => '2026-10-30']], 'metrics' => [5, 4, 9]],
        ]])]);
        $this->updateSettings(SettingGroup::Metrics, [
            'metrics__yandex_enabled' => '1', 'metrics__yandex_id' => '12345678', 'metrics__yandex_token' => 'token',
        ]);

        $this->actingAsRole(Role::ADMINISTRATOR);
        $this->get('/admin/page/analytics')->assertOk()->assertSee('Всего: визитов 15, посетителей 12, просмотров 34.');
        $http->assertSent(static fn ($request): bool => $request->hasHeader('Authorization', 'OAuth token')
            && str_contains($request->url(), 'ids=12345678'));
    }

    /**
     * Причина, требующая пояснения, помечается в конфигурации: только «другое».
     */
    public function test_only_other_complaint_reason_requires_text(): void
    {
        $reasons = collect($this->getJson('/api/site')->assertOk()->json('complaint_reasons'))
            ->mapWithKeys(static fn (array $reason): array => [$reason['value'] => $reason['requires_text']]);

        self::assertSame(['other'], $reasons->filter()->keys()->all());
    }

    /**
     * Строка отчёта GA4: дата в формате YYYYMMDD и значения сеансов, посетителей и просмотров.
     *
     * @param list<int> $values
     */
    private function ga4Row(string $date, array $values): Row
    {
        return new Row([
            'dimension_values' => [new DimensionValue(['value' => $date])],
            'metric_values' => array_map(
                static fn (int $value): MetricValue => new MetricValue(['value' => (string) $value]),
                $values,
            ),
        ]);
    }
}
