<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Enums\Social\SocialProvider;

/**
 * Ключи настроек сайта: единый источник имён, по которым код читает настройки
 * и которые описывает {@see \App\Services\Settings\SettingsSchema}. Ключи с переменной частью
 * (соцсети, счётчики, share) строятся методами из своих enum-ов.
 */
enum SettingKey: string
{
    /** Название сайта: заголовки страниц, разметка и подпись в письмах. */
    case GeneralSiteName = 'general.site_name';

    /** Картинок в тексте одной статьи. */
    case ContentArticleImages = 'content.article_images';

    /** Тегов у одной статьи. */
    case ContentArticleTags = 'content.article_tags';

    /** Фотографий в одном альбоме. */
    case ContentAlbumPhotos = 'content.album_photos';

    /** Символов в одном комментарии. */
    case ContentCommentLength = 'content.comment_length';

    /** Подписей по оси в таблице графика. */
    case ContentChartLabels = 'content.chart_labels';

    /** Рядов значений в таблице графика. */
    case ContentChartSeries = 'content.chart_series';

    /** Вариантов ответа в одном опросе. */
    case ContentPollMaxOptions = 'content.poll_max_options';

    /** Аватаров проголосовавших у каждого варианта неанонимного опроса. */
    case ContentPollPreviewVoters = 'content.poll_preview_voters';

    /** Автоскрытие: объект скрывается, когда набирается порог жалоб. */
    case ContentComplaintsAutoHide = 'content.complaints_auto_hide';

    /** Число открытых жалоб, при котором объект скрывается автоматически. */
    case ContentComplaintsThreshold = 'content.complaints_threshold';

    /** Статей на странице ленты, автора и «Моих статей». */
    case PaginationArticles = 'pagination.articles';

    /** Веток комментариев и ответов за одну подгрузку. */
    case PaginationComments = 'pagination.comments';

    /** Альбомов на странице «Мои альбомы». */
    case PaginationAlbums = 'pagination.albums';

    /** Проголосовавших за одну подгрузку полного списка опроса. */
    case PaginationPollVoters = 'pagination.poll_voters';

    /** Статей в RSS-ленте. */
    case PaginationFeed = 'pagination.feed';

    /** Строк в списках админки. */
    case PaginationAdmin = 'pagination.admin';

    /** Строк в рейтингах админки. */
    case PaginationRating = 'pagination.rating';

    /** Строк в топах страницы статистики. */
    case PaginationStatisticsTop = 'pagination.statistics_top';

    /** Авторов в топе на главной странице админки. */
    case PaginationTopAuthors = 'pagination.top_authors';

    /** Подсказок в поле тегов. */
    case PaginationTagSuggestions = 'pagination.tag_suggestions';

    /** Срок хранения просмотров статей в месяцах: более старые просмотры удаляются. */
    case StatisticsViewsRetentionMonths = 'statistics.views_retention_months';

    /** Запрет индексации сайта: пока он включён, robots.txt запрещает обход всего сайта. */
    case SeoNoindex = 'seo.noindex';

    /** Заголовок главной страницы; пустой — название сайта. */
    case SeoHomeTitle = 'seo.home_title';

    /** Описание главной страницы для поисковиков и соцсетей. */
    case SeoHomeDescription = 'seo.home_description';

    /** Картинка для соцсетей, когда у страницы нет своей. */
    case SeoDefaultImage = 'seo.default_image';

    /** Теги Open Graph для соцсетей. */
    case SeoOgEnabled = 'seo.og_enabled';

    /** Карточки Twitter для страниц. */
    case SeoTwitterEnabled = 'seo.twitter_enabled';

    /** Аккаунт сайта в Twitter для карточек. */
    case SeoTwitterSite = 'seo.twitter_site';

    /** Код подтверждения сайта для Яндекс Вебмастера. */
    case SeoVerificationYandex = 'seo.verification_yandex';

    /** Код подтверждения сайта для Google Search Console. */
    case SeoVerificationGoogle = 'seo.verification_google';

    /** RSS-лента: пока она включена, на страницах есть ссылка на неё. */
    case SeoRssEnabled = 'seo.rss_enabled';

    /** Дополнительные строки robots.txt. */
    case SeoRobotsExtra = 'seo.robots_extra';

    /** Кнопка «поделиться» во ВКонтакте. */
    case ShareVk = 'share.vk';

    /** Кнопка «поделиться» в Одноклассниках. */
    case ShareOk = 'share.ok';

    /** Кнопка «поделиться» в Telegram. */
    case ShareTelegram = 'share.telegram';

    /** Кнопка «поделиться» в Facebook. */
    case ShareFacebook = 'share.facebook';

    /** Кнопка «поделиться» в X. */
    case ShareX = 'share.x';

    /** Счётчик Яндекс Метрики подключён. */
    case MetricsYandexEnabled = 'metrics.yandex_enabled';

    /** Номер счётчика Яндекс Метрики. */
    case MetricsYandexId = 'metrics.yandex_id';

    /** OAuth-токен доступа к Stat API Яндекс Метрики: нужен для отчётов в админке. */
    case MetricsYandexToken = 'metrics.yandex_token';

    /** Счётчик GA4 подключён. */
    case MetricsGa4Enabled = 'metrics.ga4_enabled';

    /** Идентификатор измерения GA4 вида G-XXXX. */
    case MetricsGa4Id = 'metrics.ga4_id';

    /** Идентификатор ресурса GA4 для Data API: нужен для отчётов в админке. */
    case MetricsGa4Property = 'metrics.ga4_property';

    /** JSON-ключ сервисного аккаунта GA4: нужен для отчётов в админке. */
    case MetricsGa4Credentials = 'metrics.ga4_credentials';

    /** Контейнер Google Tag Manager подключён. */
    case MetricsGtmEnabled = 'metrics.gtm_enabled';

    /** Контейнер Google Tag Manager вида GTM-XXXX. */
    case MetricsGtmId = 'metrics.gtm_id';

    /** Счётчик VK подключён. */
    case MetricsVkEnabled = 'metrics.vk_enabled';

    /** Номер счётчика VK. */
    case MetricsVkId = 'metrics.vk_id';

    /** Пиксель Meta подключён. */
    case MetricsMetaEnabled = 'metrics.meta_enabled';

    /** Номер пикселя Meta. */
    case MetricsMetaId = 'metrics.meta_id';

    /** Регион правил регистрации: от него зависят согласия и документы. */
    case LegalRegion = 'legal.region';

    /** Режим мультиязычности: один язык или несколько. */
    case LocalizationMode = 'localization.mode';

    /** Базовый домен языковых поддоменов; нужен в режиме поддоменов. */
    case LocalizationBaseDomain = 'localization.base_domain';

    /** Facebook в анкете пользователя. */
    case ProfileNetworkFacebook = 'profile.network_facebook';

    /** X в анкете пользователя. */
    case ProfileNetworkX = 'profile.network_x';

    /** Вход через VK ID включён. */
    case SocialVkIdEnabled = 'social.vkid.enabled';

    /** Идентификатор приложения VK ID. */
    case SocialVkIdClientId = 'social.vkid.client_id';

    /** Секрет приложения VK ID. */
    case SocialVkIdClientSecret = 'social.vkid.client_secret';

    /** Вход через Яндекс ID включён. */
    case SocialYandexEnabled = 'social.yandex.enabled';

    /** Идентификатор приложения Яндекс ID. */
    case SocialYandexClientId = 'social.yandex.client_id';

    /** Секрет приложения Яндекс ID. */
    case SocialYandexClientSecret = 'social.yandex.client_secret';

    /** Вход через Google включён. */
    case SocialGoogleEnabled = 'social.google.enabled';

    /** Идентификатор клиента Google OAuth. */
    case SocialGoogleClientId = 'social.google.client_id';

    /** Секрет клиента Google OAuth. */
    case SocialGoogleClientSecret = 'social.google.client_secret';

    /** Вход через Apple включён. */
    case SocialAppleEnabled = 'social.apple.enabled';

    /** Идентификатор службы Apple: Services ID. */
    case SocialAppleClientId = 'social.apple.client_id';

    /** Секрет входа Apple: JWT, подписанный закрытым ключом службы. */
    case SocialAppleClientSecret = 'social.apple.client_secret';

    /** Вход через Facebook включён. */
    case SocialFacebookEnabled = 'social.facebook.enabled';

    /** Идентификатор приложения Facebook. */
    case SocialFacebookClientId = 'social.facebook.client_id';

    /** Секрет приложения Facebook. */
    case SocialFacebookClientSecret = 'social.facebook.client_secret';

    /** Вход через Telegram включён. */
    case SocialTelegramEnabled = 'social.telegram.enabled';

    /** Токен бота Telegram: им проверяется подпись входа. */
    case SocialTelegramBotToken = 'social.telegram.bot_token';

    /** Отправка писем через SMTP; выключенная отправка пишет письма транспортом из окружения. */
    case MailEnabled = 'mail.enabled';

    /** Адрес SMTP-сервера. */
    case MailHost = 'mail.host';

    /** Порт SMTP-сервера. */
    case MailPort = 'mail.port';

    /** Шифрование соединения с SMTP-сервером. */
    case MailEncryption = 'mail.encryption';

    /** Логин SMTP-сервера. */
    case MailUsername = 'mail.username';

    /** Пароль SMTP-сервера. */
    case MailPassword = 'mail.password';

    /** Адрес отправителя писем. */
    case MailFromAddress = 'mail.from_address';

    /** Имя отправителя писем. */
    case MailFromName = 'mail.from_name';

    /** Хранилище медиафайлов: папка сайта или S3-совместимое хранилище. */
    case StorageDriver = 'storage.driver';

    /** Ключ доступа к S3. */
    case StorageS3Key = 'storage.s3.key';

    /** Секретный ключ доступа к S3. */
    case StorageS3Secret = 'storage.s3.secret';

    /** Регион S3-хранилища. */
    case StorageS3Region = 'storage.s3.region';

    /** Бакет S3-хранилища. */
    case StorageS3Bucket = 'storage.s3.bucket';

    /** Адрес S3-сервера; пустой — адрес хранилища по умолчанию. */
    case StorageS3Endpoint = 'storage.s3.endpoint';

    /** Публичный адрес файлов в S3; пустой — адрес хранилища по умолчанию. */
    case StorageS3Url = 'storage.s3.url';

    /** Адреса S3 в виде пути к бакету. */
    case StorageS3PathStyle = 'storage.s3.path_style';

    /**
     * Ключ включения провайдера входа: social.{провайдер}.enabled.
     */
    public static function socialEnabled(SocialProvider $provider): self
    {
        return self::from('social.' . $provider->value . '.enabled');
    }

    /**
     * Ключ, без которого вход не работает: идентификатор приложения OAuth или токен бота Telegram.
     */
    public static function socialCredential(SocialProvider $provider): self
    {
        return self::from('social.' . $provider->value . '.' . ($provider->isOAuth() ? 'client_id' : 'bot_token'));
    }

    /**
     * Ключ секрета приложения OAuth-провайдера.
     */
    public static function socialClientSecret(SocialProvider $provider): self
    {
        return self::from('social.' . $provider->value . '.client_secret');
    }

    /**
     * Ключ включения счётчика или пикселя: metrics.{счётчик}_enabled.
     */
    public static function counterEnabled(Counter $counter): self
    {
        return self::from('metrics.' . $counter->value . '_enabled');
    }

    /**
     * Ключ номера счётчика или пикселя: metrics.{счётчик}_id, для GA4 и GTM это идентификатор измерения.
     */
    public static function counterId(Counter $counter): self
    {
        return self::from('metrics.' . $counter->value . '_id');
    }

    /**
     * Ключ включения кнопки «поделиться» в сети: share.{сеть}.
     */
    public static function share(ShareNetwork $network): self
    {
        return self::from('share.' . $network->value);
    }
}
