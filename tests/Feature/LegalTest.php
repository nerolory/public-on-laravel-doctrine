<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Role;
use App\Enums\Legal\ConsentType;
use App\Enums\Settings\SettingGroup;
use App\Services\Legal\LegalDocumentService;
use App\Services\Legal\Policies\UnitedStatesPolicy;
use Database\Seeders\LegalDocumentSeeder;
use Tests\DoctrineTestCase;

/**
 * Правовые документы: тексты по региону, согласия при регистрации, новые редакции, отзыв и экспорт данных.
 */
final class LegalTest extends DoctrineTestCase
{
    /**
     * Условия показываются для выбранного региона: возраст, баннер cookies и экспорт данных.
     */
    public function test_terms_follow_the_selected_region(): void
    {
        $this->getJson('/api/legal')->assertOk()
            ->assertJson(['region' => 'ru', 'minimum_age' => 14, 'cookie_banner' => true, 'data_export' => false])
            ->assertJsonCount(6, 'documents')
            ->assertJsonPath('documents.0.required', true);

        $this->setRegion('us');
        $terms = $this->getJson('/api/legal')
            ->assertJson(['region' => 'us', 'minimum_age' => 13, 'cookie_banner' => false, 'data_export' => true])
            ->json();
        self::assertSame(['personal_data', 'site_rules', 'age', 'mailing'], array_column($terms['documents'], 'type'));
        self::assertSame(
            'I confirm that I am at least 13 years old.',
            collect($terms['documents'])->firstWhere('type', 'age')['text'],
        );
    }

    /**
     * Регистрация требует согласий региона и записывает их вместе с браузером.
     */
    public function test_registration_requires_region_consents_and_records_them(): void
    {
        $this->registerUser($this->registration())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consents.personal_data', 'consents.site_rules', 'consents.age']);

        $this->registerUser(
            $this->registration(['personal_data' => true, 'site_rules' => true, 'age' => true, 'mailing' => true]),
            ['User-Agent' => 'Test browser'],
        )->assertCreated();
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT d.type, c.is_agree, c.user_agent FROM consents c '
            . 'JOIN legal_documents d ON d.id = c.document_id ORDER BY d.type',
        );
        self::assertSame(['age', 'mailing', 'personal_data', 'site_rules'], array_column($rows, 'type'));
        self::assertSame('Test browser', $rows[0]['user_agent']);
        $this->getJson('/api/user/consents')->assertOk()->assertJsonCount(0, 'pending');
    }

    /**
     * Новая редакция правил блокирует публикацию, пока пользователь её не примет.
     */
    public function test_new_rules_block_publishing_until_accepted(): void
    {
        $this->registerUser($this->registration(['personal_data' => true, 'site_rules' => true, 'age' => true]))
            ->assertCreated();
        $this->postJson('/api/articles', ['title' => 'Первая', 'content' => 'Текст'])->assertCreated();

        $this->app->make(LegalDocumentService::class)
            ->publish(ConsentType::SiteRules, 'ru', 'Правила сайта', 'Новая редакция правил.');
        $this->postJson('/api/articles', ['title' => 'Вторая', 'content' => 'Текст'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'consents_required')
            ->assertJsonPath('documents.0.type', 'site_rules')
            ->assertJsonPath('documents.0.version', 2);

        $this->postJson('/api/user/consents', ['accept' => ['site_rules']])->assertOk()->assertJsonCount(0, 'pending');
        $this->postJson('/api/articles', ['title' => 'Вторая', 'content' => 'Текст'])->assertCreated();
    }

    /**
     * Согласия на cookies принимаются и отзываются в настройках подписок в регионе, где они предусмотрены.
     */
    public function test_cookie_consents_can_be_accepted_and_revoked_in_settings(): void
    {
        $this->registerUser($this->registration(['personal_data' => true, 'site_rules' => true, 'age' => true]))
            ->assertCreated();
        $this->postJson('/api/user/consents', [
            'accept' => ['analytics_cookies', 'marketing_cookies'],
        ])->assertOk();
        $this->assertCookieConsents(analytics: true, marketing: true);

        $this->postJson('/api/user/consents', ['revoke' => ['analytics_cookies']])->assertOk();
        $this->assertCookieConsents(analytics: false, marketing: true);
    }

    /**
     * Согласие, документ которого в регионе не опубликован, отклоняется проверкой: в США баннера cookies нет.
     */
    public function test_consent_without_regional_document_is_rejected(): void
    {
        $this->setRegion(UnitedStatesPolicy::REGION);
        $this->registerUser($this->registration(['personal_data' => true, 'site_rules' => true, 'age' => true]))
            ->assertCreated();

        $this->postJson('/api/user/consents', ['accept' => ['analytics_cookies']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accept.0');
    }

    /**
     * Неизвестный вид согласия в форме регистрации — ошибка поля consents.
     */
    public function test_registration_rejects_unknown_consent(): void
    {
        $this->registerUser($this->registration([
            'personal_data' => true, 'site_rules' => true, 'age' => true, 'newsletter_v2' => true,
        ]))->assertUnprocessable()->assertJsonValidationErrors('consents');
    }

    /**
     * Повторный запуск сидера заглушек не создаёт новых версий документов.
     */
    public function test_legal_seeder_rerun_creates_no_new_versions(): void
    {
        $before = $this->documentRowCount();

        $this->runSeeder(LegalDocumentSeeder::class);

        self::assertSame($before, $this->documentRowCount());
        self::assertSame(1, $this->documentVersions('analytics_cookies', 'ru'));
    }

    /**
     * Отзыв в настройках доступен только для добровольных согласий.
     */
    public function test_only_voluntary_consents_are_revoked_in_settings(): void
    {
        $this->registerUser($this->registration([
            'personal_data' => true, 'site_rules' => true, 'age' => true, 'mailing' => true,
        ]))
            ->assertCreated();
        $this->postJson('/api/user/consents', ['revoke' => ['mailing']])->assertOk();
        $mailing = collect($this->getJson('/api/user/consents')->json('items'))->firstWhere('type', 'mailing');
        self::assertFalse($mailing['agreed']);
        $this->postJson('/api/user/consents', ['revoke' => ['personal_data']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('revoke.0');
    }

    /**
     * При удалении аккаунта отозванные согласия фиксируются отказом, остальные сохраняют последнее значение.
     */
    public function test_account_deletion_revokes_selected_consents(): void
    {
        $this->registerUser($this->registration(['personal_data' => true, 'site_rules' => true, 'age' => true]))
            ->assertCreated();
        $this->deleteJson('/api/user', ['password' => self::PASSWORD, 'revoke' => ['personal_data', 'site_rules']])
            ->assertOk();

        $latest = $this->em->getConnection()->fetchAllKeyValue(
            'SELECT d.type, c.is_agree FROM consents c JOIN legal_documents d ON d.id = c.document_id '
            . 'WHERE c.id IN (SELECT MAX(id) FROM consents GROUP BY document_id)',
        );
        self::assertFalse((bool) $latest['personal_data']);
        self::assertFalse((bool) $latest['site_rules']);
        self::assertTrue((bool) $latest['age']);
    }

    /**
     * Экспорт данных доступен только в регионах, где он предусмотрен правилами.
     */
    public function test_data_export_depends_on_region(): void
    {
        $this->registerUser($this->registration(['personal_data' => true, 'site_rules' => true, 'age' => true]))
            ->assertCreated();
        $this->getJson('/api/user/export')->assertNotFound();

        $this->setRegion('eu');
        $this->getJson('/api/user/export')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="my-data.json"')
            ->assertJsonPath('account.email', 'new@example.test')
            ->assertJsonCount(3, 'consents');
    }

    /**
     * Публикация новой редакции создаёт версию; повтор того же текста версию не создаёт.
     */
    public function test_admin_publishes_document_versions(): void
    {
        $this->actingAsRole(Role::ADMINISTRATOR);
        $this->get('/admin/page/legal-documents')->assertOk()->assertSee(__('legal.types.personal_data'));
        $this->get('/admin/page/legal-document-form?type=site_rules&region=ru')->assertOk()->assertSee('Правила сайта');

        $this->post('/admin/content/legal-documents', [
            'type' => 'site_rules', 'region' => 'ru', 'title' => 'Правила сайта', 'text' => 'Редакция 2',
        ])->assertSessionHas('status', __('legal.admin.published', ['version' => 2]));
        self::assertSame(2, $this->documentVersions('site_rules', 'ru'));

        $this->post('/admin/content/legal-documents', [
            'type' => 'site_rules', 'region' => 'ru', 'title' => 'Правила сайта', 'text' => 'Редакция 2',
        ])->assertSessionHas('status', __('legal.admin.published', ['version' => 2]));
        self::assertSame(2, $this->documentVersions('site_rules', 'ru'), 'Тот же текст не создаёт новую версию.');

        $this->post('/admin/content/legal-documents', [
            'type' => 'site_rules', 'region' => 'mars', 'title' => 'X', 'text' => 'Y',
        ])->assertSessionHasErrors('region');

        $this->flushSession();
        $this->actingAsRole(Role::MODERATOR, 'moderator2@example.test');
        $this->get('/admin/page/legal-documents')->assertForbidden();
    }

    /**
     * Список документов делится на страницы: видов на регионы 6 × 4 = 24 строки, по 5 на странице.
     */
    public function test_legal_documents_list_is_paginated(): void
    {
        $this->updateSettings(SettingGroup::Pagination, ['pagination__admin' => 5]);
        $this->actingAsRole(Role::ADMINISTRATOR);

        $first = $this->get('/admin/page/legal-documents')->assertOk()
            ->assertSee('page=2', escape: false)
            ->getContent();
        $last = $this->get('/admin/page/legal-documents?page=5')->assertOk()->getContent();

        self::assertSame(5, $this->documentRows($first));
        self::assertSame(4, $this->documentRows($last));
    }

    /**
     * Заполняет документы заглушками для всех регионов.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLegalDocuments();
    }

    /**
     * Регистрационные поля для запроса: согласия передаются как есть.
     *
     * @param array<string, bool> $consents
     *
     * @return array<string, mixed>
     */
    private function registration(array $consents = []): array
    {
        return ['name' => 'Новый', 'email' => 'new@example.test', 'consents' => $consents];
    }

    /**
     * Меняет регион правил в настройках юридической группы.
     */
    private function setRegion(string $region): void
    {
        $this->updateSettings(SettingGroup::Legal, ['legal__region' => $region]);
    }

    /**
     * Общее число строк документов в базе.
     */
    private function documentRowCount(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM legal_documents');
    }

    /**
     * Проверяет состояние согласий на cookies в настройках подписок текущего региона.
     */
    private function assertCookieConsents(bool $analytics, bool $marketing): void
    {
        $items = collect($this->getJson('/api/user/consents')->assertOk()->json('items'));
        self::assertSame($analytics, $items->firstWhere('type', 'analytics_cookies')['agreed']);
        self::assertSame($marketing, $items->firstWhere('type', 'marketing_cookies')['agreed']);
    }

    /**
     * Число версий документа вида в регионе.
     */
    private function documentVersions(string $type, string $region): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM legal_documents WHERE type = ? AND region = ?',
            [$type, $region],
        );
    }

    /**
     * Число строк таблицы документов на странице: у каждой строки есть ссылка «Изменить».
     */
    private function documentRows(string $html): int
    {
        return substr_count($html, 'title="' . __('legal.admin.edit') . '"');
    }
}
