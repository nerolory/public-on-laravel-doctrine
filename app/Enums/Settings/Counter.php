<?php

declare(strict_types=1);

namespace App\Enums\Settings;

/**
 * Счётчик аналитики или пиксель рекламы. Значение — часть ключей настроек metrics.{счётчик}_* и имени провайдера.
 */
enum Counter: string
{
    /** Яндекс Метрика: посещаемость сайта. */
    case Yandex = 'yandex';

    /** Google Analytics 4: посещаемость сайта. */
    case Ga4 = 'ga4';

    /** Google Tag Manager: контейнер тегов. */
    case Gtm = 'gtm';

    /** VK: пиксель рекламы. */
    case Vk = 'vk';

    /** Meta: пиксель рекламы. */
    case Meta = 'meta';

    /**
     * Категория cookies, на которую нужно согласие посетителя: аналитика или маркетинг.
     */
    public function consent(): string
    {
        return match ($this) {
            self::Yandex, self::Ga4, self::Gtm => 'analytics',
            self::Vk, self::Meta => 'marketing',
        };
    }
}
