<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\DTO\Seo\ShareLinkData;
use App\Enums\Settings\SettingKey;
use App\Enums\Settings\ShareNetwork;
use App\Services\Settings\SettingsService;
use App\Support\Seo\SiteUrls;
use Illuminate\Support\Collection;

/**
 * Ссылки «поделиться» без сторонних скриптов: включённые в настройках сети и готовые адреса.
 */
final class ShareService
{
    /**
     * @param SettingsService       $settings  настройки: какие сети включены
     * @param SiteUrls              $urls      абсолютные адреса
     * @param array<string, string> $templates шаблоны адресов по сетям из config/services.php;
     *                                         {url} и {title} подставляются закодированными
     */
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SiteUrls $urls,
        private readonly array $templates,
    ) {
    }

    /**
     * Ссылки на публикацию страницы во включённых сетях.
     *
     * @return Collection<int, ShareLinkData>
     */
    public function links(string $path, string $title): Collection
    {
        $replace = ['{url}' => rawurlencode($this->urls->to($path)), '{title}' => rawurlencode($title)];

        return collect($this->templates)
            ->filter(fn (string $template, string $network): bool => $this->settings->boolean(
                SettingKey::share(ShareNetwork::from($network)),
            ))
            ->map(static fn (string $template, string $network): ShareLinkData => new ShareLinkData(
                $network,
                strtr($template, $replace),
            ))
            ->values();
    }
}
