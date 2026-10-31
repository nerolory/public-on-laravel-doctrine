<?php

declare(strict_types=1);

namespace App\DTO\Seo;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Тег meta: обычный (name) или свойство Open Graph (property).
 *
 * @implements Arrayable<string, string>
 */
final readonly class MetaTagData implements Arrayable
{
    /**
     * @param string      $content  значение
     * @param string|null $name     имя тега: description, robots, twitter:*
     * @param string|null $property свойство Open Graph: og:*, article:*
     */
    public function __construct(
        public string $content,
        public ?string $name = null,
        public ?string $property = null,
    ) {
    }

    /**
     * Атрибуты тега для ответа API.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter(
            ['name' => $this->name, 'property' => $this->property, 'content' => $this->content],
            static fn (?string $value): bool => $value !== null,
        );
    }
}
