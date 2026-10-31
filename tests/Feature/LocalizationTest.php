<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\Seo\AlternateUrlData;
use App\Entities\Role;
use App\Enums\Settings\SettingGroup;
use App\Support\Seo\SiteUrls;
use LogicException;
use Tests\DoctrineTestCase;

/**
 * Языки сайта: режим и заголовки языка, управление языками в админке и совпадение файлов переводов.
 */
final class LocalizationTest extends DoctrineTestCase
{
    /**
     * Язык по умолчанию всегда включён, поэтому без языков в базе сайт не работает: это ошибка настройки.
     */
    public function test_site_without_languages_is_a_configuration_error(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM languages');
        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No enabled language');

        $this->getJson('/api/i18n');
    }

    /**
     * В режиме одного языка доступен только основной; в режиме префиксов включённые языки и заголовок языка.
     */
    public function test_mode_controls_enabled_languages_and_request_locale(): void
    {
        $this->getJson('/api/i18n')->assertJson(['mode' => 'single', 'default' => 'ru'])
            ->assertJsonCount(1, 'languages');
        $this->getJson('/api/articles', ['X-Locale' => 'en'])->assertHeader('Content-Language', 'ru');

        $this->setMode('prefix');
        $this->getJson('/api/i18n')->assertJson([
            'mode' => 'prefix',
            'languages' => [['code' => 'ru', 'name' => 'Русский'], ['code' => 'en', 'name' => 'English']],
        ]);
        $this->getJson('/api/articles', ['X-Locale' => 'en'])->assertHeader('Content-Language', 'en');
        $this->getJson('/api/articles', ['X-Locale' => 'fr'])->assertHeader('Content-Language', 'ru');
        $this->postJson('/api/login', [], ['X-Locale' => 'en'])
            ->assertJsonPath('errors.email.0', 'The email field is required.');

        $this->getJson('/api/i18n/en')->assertOk()->assertJsonPath('site.name', 'Words and Meanings');
        $this->getJson('/api/i18n/de')->assertNotFound();
    }

    /**
     * В режиме поддоменов адреса языков ведут на поддомены основного домена с его портом.
     * Язык запроса передаётся заголовком: фронтенд определяет его по поддомену.
     */
    public function test_subdomain_mode_builds_language_addresses(): void
    {
        config(['app.url' => 'https://example.test:8443']);
        $this->setMode('subdomain', 'example.test');
        $this->getJson('/api/i18n')->assertJson([
            'mode' => 'subdomain', 'base_domain' => 'example.test', 'default' => 'ru',
        ]);

        $urls = $this->app->make(SiteUrls::class);
        self::assertSame('https://example.test:8443/articles/5', $urls->to('/articles/5'));
        self::assertSame('https://example.test:8443/articles/5', $urls->to('/articles/5', 'ru'));
        self::assertSame('https://en.example.test:8443/articles/5', $urls->to('/articles/5', 'en'));
        $alternates = $urls->alternates('/articles/5')
            ->mapWithKeys(static fn (AlternateUrlData $url): array => [$url->hreflang => $url->href])
            ->all();
        self::assertEqualsCanonicalizing([
            'ru' => 'https://example.test:8443/articles/5',
            'en' => 'https://en.example.test:8443/articles/5',
            'x-default' => 'https://example.test:8443/articles/5',
        ], $alternates);
        $this->getJson('/api/articles', ['X-Locale' => 'en'])->assertHeader('Content-Language', 'en');
    }

    /**
     * Режим поддоменов без основного домена не сохраняется: адреса языков без домена построить нельзя.
     */
    public function test_subdomain_mode_requires_base_domain(): void
    {
        $this->actingAsRole(Role::OWNER);
        $this->put('/admin/content/settings/localization', [
            'localization__mode' => 'subdomain', 'localization__base_domain' => '',
        ])->assertSessionHasErrors('localization__base_domain');
        $this->getJson('/api/i18n')->assertJson(['mode' => 'single']);
    }

    /**
     * Администратор добавляет, меняет и отключает языки; основной язык защищён от удаления.
     */
    public function test_admin_manages_languages_and_default_language(): void
    {
        $this->setMode('prefix');
        $this->actingAsRole(Role::ADMINISTRATOR);

        $this->get('/admin/page/languages')->assertOk()->assertSee('English');
        $this->get('/admin/page/language-form')->assertOk()
            ->assertSee(__('languages.hints.file'))
            ->assertSee('value="ru"', escape: false);
        $this->post('/admin/content/languages', ['code' => 'de', 'name' => 'Deutsch', 'file' => 'en', 'enabled' => '1'])
            ->assertSessionHasNoErrors();
        $this->post('/admin/content/languages', ['code' => 'de', 'name' => 'Deutsch', 'file' => 'en'])
            ->assertSessionHasErrors('code');
        $this->post('/admin/content/languages', ['code' => 'fr', 'name' => 'Français', 'file' => 'fr'])
            ->assertSessionHasErrors('file');
        $this->post('/admin/content/languages', ['code' => 'Fr!', 'name' => 'X', 'file' => 'en'])
            ->assertSessionHasErrors('code');
        $this->getJson('/api/i18n')->assertJsonCount(3, 'languages');

        $this->put('/admin/content/languages/en', [
            'name' => 'English', 'file' => 'en', 'enabled' => '1', 'is_default' => '1',
        ])->assertSessionHasNoErrors();
        $this->getJson('/api/i18n')->assertJsonPath('default', 'en');
        $this->delete('/admin/content/languages/en')->assertSessionHasErrors('language');
        $this->delete('/admin/content/languages/de')->assertRedirect('/admin/page/languages');
        $this->put('/admin/content/languages/ru', ['name' => 'Русский', 'file' => 'ru', 'enabled' => '0'])
            ->assertSessionHasNoErrors();
        $this->getJson('/api/i18n')->assertJsonCount(1, 'languages')->assertJsonPath('mode', 'single');

        $this->flushSession();
        $this->actingAsRole(Role::MODERATOR, 'moderator2@example.test');
        $this->get('/admin/page/languages')->assertForbidden();
        $this->delete('/admin/content/languages/ru')->assertForbidden();
    }

    /**
     * Русский и английский переводы содержат одинаковый набор ключей.
     */
    public function test_russian_and_english_translations_have_the_same_keys(): void
    {
        $ru = $this->flatKeys(lang_path('ru'));
        $en = $this->flatKeys(lang_path('en'));

        self::assertSame([], array_values(array_diff($ru, $en)), 'Ключи есть в ru, но нет в en.');
        self::assertSame([], array_values(array_diff($en, $ru)), 'Ключи есть в en, но нет в ru.');
    }

    /**
     * Сообщения валидации по умолчанию на русском языке.
     */
    public function test_validation_messages_are_russian_by_default(): void
    {
        $this->postJson('/api/login', [])->assertJsonPath('errors.email.0', 'Поле «email» обязательно для заполнения.');
    }

    /**
     * Ключи всех файлов папки переводов в виде «файл.путь.к.ключу», отсортированные.
     *
     * @return list<string>
     */
    private function flatKeys(string $directory): array
    {
        $keys = [];
        foreach (glob($directory . '/*.php') as $file) {
            $keys = [...$keys, ...$this->leafKeys(require $file, basename($file, '.php') . '.')];
        }
        sort($keys);

        return $keys;
    }

    /**
     * Листовые ключи вложенного массива перевода с префиксом пути.
     *
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
    private function leafKeys(array $values, string $prefix): array
    {
        $keys = [];
        foreach ($values as $key => $value) {
            if (is_array($value) && $value !== []) {
                $keys = [...$keys, ...$this->leafKeys($value, $prefix . $key . '.')];
                continue;
            }
            $keys[] = $prefix . $key;
        }

        return $keys;
    }

    /**
     * Меняет режим языков сайта и основной домен для поддоменов; по умолчанию домен очищается.
     */
    private function setMode(string $mode, string $baseDomain = ''): void
    {
        $this->updateSettings(SettingGroup::Localization, [
            'localization__mode' => $mode, 'localization__base_domain' => $baseDomain,
        ]);
    }
}
