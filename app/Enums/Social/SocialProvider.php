<?php

declare(strict_types=1);

namespace App\Enums\Social;

use Illuminate\Support\Collection;

/**
 * Провайдер входа через соцсеть. Значение — имя драйвера Socialite и часть адреса входа и ключей настроек
 * social.{провайдер}.*; порядок вариантов задаёт порядок кнопок входа.
 */
enum SocialProvider: string
{
    /** Вход через VK ID из SocialiteProviders. */
    case VkId = 'vkid';

    /** Вход через Яндекс ID из SocialiteProviders. */
    case Yandex = 'yandex';

    /** Вход через Google: встроенный драйвер Socialite. */
    case Google = 'google';

    /** Вход через Apple из SocialiteProviders. */
    case Apple = 'apple';

    /** Вход через Facebook: встроенный драйвер Socialite. */
    case Facebook = 'facebook';

    /** Вход через Telegram: подпись проверяет сайт, без Socialite. */
    case Telegram = 'telegram';

    /**
     * Провайдеры, вход через которых идёт по OAuth через Socialite.
     *
     * @return Collection<int, self>
     */
    public static function oauth(): Collection
    {
        return collect(self::cases())->filter(static fn (self $provider): bool => $provider->isOAuth())->values();
    }

    /**
     * Входит ли провайдер по OAuth через Socialite.
     */
    public function isOAuth(): bool
    {
        return $this !== self::Telegram;
    }

    /**
     * Есть ли у включения провайдера подсказка в админке: её текст хранится в lang/{locale}/settings.php.
     */
    public function hasHint(): bool
    {
        return in_array($this, [self::VkId, self::Facebook], strict: true);
    }
}
