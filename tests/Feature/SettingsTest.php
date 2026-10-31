<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Role;
use App\Entities\Setting;
use App\Enums\Settings\SettingGroup;
use App\Enums\Settings\SettingKey;
use App\Jobs\Seo\GenerateSitemapJob;
use App\Listeners\Mail\ApplyMailSettings;
use App\Mail\TestMail;
use App\Services\Settings\SettingsSchema;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\DoctrineTestCase;

/**
 * Настройки сайта в админке: доступ, сохранение и кеш, SMTP, проверка схемой и карта сайта.
 */
final class SettingsTest extends DoctrineTestCase
{
    /**
     * Настройки открыты владельцу и администратору, остальные роли получают 403.
     */
    public function test_only_owner_and_administrator_manage_settings(): void
    {
        foreach ([Role::MODERATOR, Role::CONTENT_MANAGER] as $index => $role) {
            $this->flushSession();
            $this->actingAsRole($role, "denied{$index}@example.test");
            $this->get('/admin/page/site-settings')->assertForbidden();
            $this->put('/admin/content/settings/general', ['general__site_name' => 'X'])->assertForbidden();
        }
        foreach ([Role::OWNER, Role::ADMINISTRATOR] as $index => $role) {
            $this->flushSession();
            $this->actingAsRole($role, "allowed{$index}@example.test");
            $this->get('/admin/page/site-settings?group=mail')->assertOk()->assertSee(__('settings.fields.mail.host'));
        }
    }

    /**
     * Сохранённые настройки сразу видны сервису; пустой секрет сохраняет прежний пароль, в форме пароль не выводится.
     */
    public function test_settings_are_saved_cached_and_reset_after_change(): void
    {
        $this->actingAsRole(Role::ADMINISTRATOR);
        $settings = $this->app->make(SettingsService::class);
        self::assertSame('Слова и смыслы', $settings->get(SettingKey::GeneralSiteName));
        self::assertSame(587, $settings->get(SettingKey::MailPort));

        $this->put('/admin/content/settings/mail', [
            'mail__enabled' => '1', 'mail__host' => 'smtp.example.test', 'mail__port' => '2525',
            'mail__encryption' => 'ssl', 'mail__username' => 'robot', 'mail__password' => 'secret-1',
            'mail__from_address' => 'robot@example.test', 'mail__from_name' => 'Сайт',
        ])->assertRedirect('/admin/page/site-settings?group=mail')->assertSessionHasNoErrors();

        self::assertTrue($settings->get(SettingKey::MailEnabled));
        self::assertSame(2525, $settings->get(SettingKey::MailPort));
        self::assertSame('secret-1', $settings->get(SettingKey::MailPassword));

        $this->put('/admin/content/settings/mail', [
            'mail__enabled' => '0', 'mail__host' => 'smtp.example.test', 'mail__port' => '2525',
            'mail__encryption' => 'tls', 'mail__password' => '',
            'mail__from_address' => 'robot@example.test', 'mail__from_name' => 'Сайт',
        ])->assertSessionHasNoErrors();
        self::assertFalse($settings->get(SettingKey::MailEnabled));
        self::assertSame(
            'secret-1',
            $settings->get(SettingKey::MailPassword),
            'Пустой секрет не затирает сохранённое значение.',
        );

        $page = $this->get('/admin/page/site-settings?group=mail')->assertOk()->getContent();
        self::assertStringNotContainsString('secret-1', $page);
    }

    /**
     * Тестовое письмо уходит текущему администратору; модератор его отправить не может.
     */
    public function test_test_mail_is_sent_to_current_admin(): void
    {
        Mail::fake();
        $admin = $this->actingAsRole(Role::ADMINISTRATOR, 'admin@example.test');
        $this->post('/admin/content/settings/mail/test')
            ->assertRedirect('/admin/page/site-settings?group=mail')
            ->assertSessionHas('status', __('mail.test.logged'));
        Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo($admin->getEmail()));

        $this->flushSession();
        $this->actingAsRole(Role::MODERATOR, 'moderator2@example.test');
        $this->post('/admin/content/settings/mail/test')->assertForbidden();
    }

    /**
     * Включённые SMTP-настройки попадают в почтовый менеджер; новые параметры создают новый транспорт.
     */
    public function test_smtp_settings_are_applied_to_mail_manager(): void
    {
        $this->updateSettings(SettingGroup::Mail, [
            'mail__enabled' => '1', 'mail__host' => 'smtp.example.test', 'mail__port' => '465',
            'mail__encryption' => 'ssl', 'mail__username' => 'robot', 'mail__password' => 'secret',
            'mail__from_address' => 'robot@example.test', 'mail__from_name' => 'Сайт',
        ]);
        $this->app->forgetInstance('mail.manager');
        $this->app->make('mail.manager');

        $mailer = config('mail.default');
        self::assertStringStartsWith('site_', $mailer);
        self::assertSame(
            ['transport' => 'smtp', 'scheme' => 'smtps', 'host' => 'smtp.example.test', 'port' => 465],
            array_intersect_key(config('mail.mailers.' . $mailer), array_flip(['transport', 'scheme', 'host', 'port'])),
        );
        self::assertSame(['address' => 'robot@example.test', 'name' => 'Сайт'], config('mail.from'));

        // Новые параметры создают новый транспорт: менеджер почты строит его заново.
        $this->updateSettings(SettingGroup::Mail, [
            'mail__enabled' => '1', 'mail__host' => 'smtp2.example.test', 'mail__port' => '465',
            'mail__encryption' => 'ssl', 'mail__username' => 'robot', 'mail__password' => 'secret',
            'mail__from_address' => 'robot@example.test', 'mail__from_name' => 'Сайт',
        ]);
        $this->app->make(ApplyMailSettings::class)->apply();
        self::assertNotSame($mailer, config('mail.default'));
        self::assertSame('smtp2.example.test', config('mail.mailers.' . config('mail.default') . '.host'));
    }

    /**
     * Выключенный SMTP не меняет транспорт: используется транспорт из окружения.
     */
    public function test_disabled_smtp_uses_environment_transport(): void
    {
        $environment = config('mail.mailers.' . config('mail.default'));
        $this->updateSettings(SettingGroup::Mail, [
            'mail__enabled' => '0', 'mail__host' => '', 'mail__port' => '587', 'mail__encryption' => 'tls',
            'mail__username' => '', 'mail__password' => '', 'mail__from_address' => 'robot@example.test',
            'mail__from_name' => 'Сайт',
        ]);

        $this->app->make(ApplyMailSettings::class)->apply();

        self::assertSame($environment, config('mail.mailers.' . config('mail.default')));
    }

    /**
     * Значения вне схемы и пустые обязательные поля отклоняются; маршрута для неизвестной группы нет.
     */
    public function test_settings_are_validated_by_schema(): void
    {
        $this->actingAsRole(Role::OWNER);
        $this->put('/admin/content/settings/mail', [
            'mail__port' => '99999', 'mail__encryption' => 'rot13', 'mail__from_address' => 'not-an-email',
        ])->assertSessionHasErrors(['mail__port', 'mail__encryption', 'mail__from_address']);
        $this->put('/admin/content/settings/pagination', ['pagination__articles' => ''])
            ->assertSessionHasErrors('pagination__articles');
        $this->put('/admin/content/settings/unknown', [])->assertMethodNotAllowed();
    }

    /**
     * Сидер настроек добавляет отсутствующие ключи, заполняет пустые обязательные значения и не меняет заполненные.
     */
    public function test_settings_seeder_restores_missing_and_empty_values_only(): void
    {
        $this->em->remove($this->em->find(Setting::class, SettingKey::PaginationAdmin->value));
        $this->em->find(Setting::class, SettingKey::MailFromAddress->value)->change('');
        $this->em->find(Setting::class, SettingKey::MailFromName->value)->change(value: null);
        $this->em->find(Setting::class, SettingKey::GeneralSiteName->value)->change('Мой сайт');
        $this->em->flush();

        $this->runSeeder(SettingsSeeder::class);
        $this->runSeeder(SettingsSeeder::class);

        $this->em->clear();
        $defaults = $this->app->make(SettingsSchema::class)->defaults()->keyBy('key');
        $restored = [SettingKey::PaginationAdmin, SettingKey::MailFromAddress, SettingKey::MailFromName];
        foreach ($restored as $key) {
            self::assertSame(
                $defaults[$key->value]->value,
                $this->em->find(Setting::class, $key->value)->getValue(),
                $key->value,
            );
        }
        self::assertSame('Мой сайт', $this->em->find(Setting::class, SettingKey::GeneralSiteName->value)->getValue());
    }

    /**
     * Настройка, которой нет в базе, — ошибка конфигурации: значения по умолчанию записывает SettingsSeeder.
     */
    public function test_missing_setting_is_a_configuration_error(): void
    {
        $this->em->remove($this->em->find(Setting::class, SettingKey::PaginationAdmin->value));
        $this->em->flush();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(SettingKey::PaginationAdmin->value);
        $this->app->make(SettingsService::class)->get(SettingKey::PaginationAdmin);
    }

    /**
     * Схема описывает ровно те ключи, что перечислены в SettingKey: у каждого ключа одно описание.
     */
    public function test_schema_describes_every_setting_key(): void
    {
        $described = $this->app->make(SettingsSchema::class)->defaults()->pluck('key')->sort()->values()->all();
        $keys = collect(SettingKey::cases())->map(static fn (SettingKey $key): string => $key->value)
            ->sort()->values()->all();

        self::assertSame($keys, $described);
    }

    /**
     * Кнопка пересборки карты сайта ставит задачу в очередь только у администратора.
     */
    public function test_sitemap_button_queues_rebuild(): void
    {
        Bus::fake();
        $this->actingAsRole(Role::MODERATOR);
        $this->post('/admin/content/settings/seo/sitemap')->assertForbidden();
        Bus::assertNotDispatched(GenerateSitemapJob::class);

        $this->flushSession();
        $this->actingAsRole(Role::ADMINISTRATOR, 'admin@example.test');
        $this->post('/admin/content/settings/seo/sitemap')
            ->assertRedirect()
            ->assertSessionHas('status', __('settings.sitemap.queued'));
        Bus::assertDispatched(GenerateSitemapJob::class);
    }
}
