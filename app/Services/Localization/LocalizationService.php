<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\DTO\Localization\LocaleData;
use App\DTO\Localization\LocaleMessagesData;
use App\DTO\Localization\LocalizationConfigData;
use App\Entities\Language;
use App\Enums\Localization\LocalizationMode;
use App\Enums\Settings\SettingKey;
use App\Repositories\LanguageRepository;
use App\Services\Settings\SettingsService;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Языки сайта и режим мультиязычности. Конфигурация кешируется до изменения языков или настроек,
 * кеш сбрасывает {@see \App\Doctrine\CacheInvalidationSubscriber}.
 */
final class LocalizationService
{
    /** Ключ кеша с конфигурацией языков. */
    public const string CACHE_KEY = 'localization.config';

    /**
     * @param LanguageRepository $languages  языки
     * @param SettingsService    $settings   настройки сайта: режим и домен
     * @param Translator         $translator переводы
     * @param Cache              $cache      кеш конфигурации
     */
    public function __construct(
        private readonly LanguageRepository $languages,
        private readonly SettingsService $settings,
        private readonly Translator $translator,
        private readonly Cache $cache,
    ) {
    }

    /**
     * Конфигурация для фронтенда и middleware: режим, язык по умолчанию и включённые языки.
     */
    public function config(): LocalizationConfigData
    {
        return $this->cache->rememberForever(self::CACHE_KEY, fn (): LocalizationConfigData => $this->build());
    }

    /**
     * Есть ли у страниц языковые версии: режим допускает несколько языков и включено больше одного.
     */
    public function isMultilingual(): bool
    {
        $config = $this->config();

        return $config->mode->isMultilingual() && $config->languages->count() > 1;
    }

    /**
     * Запрошенный язык, если он включён, иначе язык по умолчанию.
     */
    public function resolve(?string $code): LocaleData
    {
        $config = $this->config();
        $requested = $code === null ? null : $this->enabledLanguage($config, $code);

        return $requested ?? $this->configDefaultLanguage($config);
    }

    /**
     * Переводы интерфейса публичной части из lang/{file}/front.php и подписи редактора статей
     * из lang/{file}/editor.php: тот же файл использует админка.
     *
     * @throws EntityNotFoundException язык не включён
     * @throws LogicException          в папке языка нет front.php или editor.php
     */
    public function messages(string $code): LocaleMessagesData
    {
        $language = $this->enabledLanguage($this->config(), $code)
            ?? throw EntityNotFoundException::fromClassNameAndIdentifier(Language::class, ['code' => $code]);
        $front = $this->translator->get('front', [], locale: $language->file, fallback: false);
        $editor = $this->translator->get('editor', [], locale: $language->file, fallback: false);

        if (! is_array($front) || ! is_array($editor)) {
            throw new LogicException('Translation files are missing for the language: ' . $language->file);
        }

        return new LocaleMessagesData($front, $editor);
    }

    /**
     * Собирает конфигурацию: в режиме одного языка остаётся только язык по умолчанию.
     */
    private function build(): LocalizationConfigData
    {
        $mode = LocalizationMode::from($this->settings->string(SettingKey::LocalizationMode));
        $enabled = $this->languages->all()
            ->filter(static fn (Language $language): bool => $language->isEnabled())
            ->values();
        $default = $this->defaultLanguage($enabled);
        $languages = $mode->isMultilingual()
            ? $enabled->map(LocaleData::fromEntity(...))
            : collect([$default]);

        return new LocalizationConfigData(
            mode: $languages->count() > 1 ? $mode : LocalizationMode::Single,
            baseDomain: $this->settings->optionalString(SettingKey::LocalizationBaseDomain),
            default: $default->code,
            languages: $languages,
        );
    }

    /**
     * Язык по умолчанию среди включённых: тот, что помечен в базе.
     *
     * @param Collection<int, Language> $enabled
     *
     * @throws LogicException среди включённых нет языка по умолчанию: его задаёт LanguageSeeder
     */
    private function defaultLanguage(Collection $enabled): LocaleData
    {
        $language = $enabled->first(static fn (Language $language): bool => $language->isDefault())
            ?? throw new LogicException('No enabled language is marked as default: LanguageSeeder must mark one.');

        return LocaleData::fromEntity($language);
    }

    /**
     * Включённый язык по коду.
     */
    private function enabledLanguage(LocalizationConfigData $config, string $code): ?LocaleData
    {
        return $config->languages->first(static fn (LocaleData $language): bool => $language->code === $code);
    }

    /**
     * Язык по умолчанию из собранной конфигурации.
     *
     * @throws LogicException язык по умолчанию не входит в список включённых
     */
    private function configDefaultLanguage(LocalizationConfigData $config): LocaleData
    {
        return $this->enabledLanguage($config, $config->default)
            ?? throw new LogicException('Default language is not in the enabled list: ' . $config->default);
    }
}
