<?php

declare(strict_types=1);

namespace App\DTO\Seo;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Тег link: канонический адрес, языковая версия или лента RSS.
 *
 * @implements Arrayable<string, string>
 */
final readonly class LinkTagData implements Arrayable
{
    /**
     * @param string      $rel      отношение: canonical или alternate
     * @param string      $href     полный адрес
     * @param string|null $hreflang язык версии страницы
     * @param string|null $type     MIME-тип ленты
     * @param string|null $title    название ленты
     */
    public function __construct(
        public string $rel,
        public string $href,
        public ?string $hreflang = null,
        public ?string $type = null,
        public ?string $title = null,
    ) {
    }

    /**
     * Атрибуты тега для ответа API; пустые не выводятся.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'rel' => $this->rel,
                'hreflang' => $this->hreflang,
                'type' => $this->type,
                'title' => $this->title,
                'href' => $this->href,
            ],
            static fn (?string $value): bool => $value !== null,
        );
    }
}
