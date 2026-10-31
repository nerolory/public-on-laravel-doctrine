<?php

declare(strict_types=1);

namespace App\DTO\Seo;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Ссылка соцсети для кнопок «поделиться» и входа.
 *
 * @implements Arrayable<string, string>
 */
final readonly class ShareLinkData implements Arrayable
{
    /**
     * @param string $network ключ соцсети или провайдера входа
     * @param string $url     адрес публикации или начала входа
     */
    public function __construct(
        public string $network,
        public string $url,
    ) {
    }

    /**
     * Ссылка для ответа API.
     *
     * @return array{network: string, url: string}
     */
    public function toArray(): array
    {
        return ['network' => $this->network, 'url' => $this->url];
    }
}
