<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Traits\ProvidesEnumValues;

/**
 * Группа настроек — вкладка страницы «Настройки» в админке. Значение совпадает с частью адреса
 * /admin/content/settings/{группа} и с ключом подписи settings.groups.{группа}; порядок случаев — порядок вкладок.
 */
enum SettingGroup: string
{
    use ProvidesEnumValues;

    /** Название сайта. */
    case General = 'general';

    /** Лимиты публикаций: картинки, теги, опросы, графики, жалобы. */
    case Content = 'content';

    /** Размеры страниц списков и рейтингов. */
    case Pagination = 'pagination';

    /** Срок хранения сырых просмотров. */
    case Statistics = 'statistics';

    /** Поисковики и соцсети: метаданные главной, индексация, RSS. */
    case Seo = 'seo';

    /** Кнопки «поделиться». */
    case Share = 'share';

    /** Счётчики и аналитика. */
    case Metrics = 'metrics';

    /** Правовой режим: регион правил регистрации. */
    case Legal = 'legal';

    /** Языки и адреса языковых версий. */
    case Localization = 'localization';

    /** Профили: дополнительные соцсети в карточке автора. */
    case Profile = 'profile';

    /** Вход через соцсети. */
    case Social = 'social';

    /** Почта: SMTP и отправитель писем. */
    case Mail = 'mail';

    /** Хранилище файлов. */
    case Storage = 'storage';

    /**
     * Шаблон дополнительного блока под формой группы: проверочное письмо у почты, пересборка карты сайта у SEO.
     */
    public function extraView(): ?string
    {
        return match ($this) {
            self::Mail => 'admin.mail-test',
            self::Seo => 'admin.sitemap',
            self::General, self::Content, self::Pagination, self::Statistics, self::Share, self::Metrics,
            self::Legal, self::Localization, self::Profile, self::Social, self::Storage => null,
        };
    }
}
