<?php

declare(strict_types=1);

namespace App\DTO\Seo;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Spatie\SchemaOrg\BaseType;

/**
 * Готовые теги <head>: фронтенд выводит их без изменений.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class HeadData implements Arrayable
{
    /**
     * @param string                       $title  заголовок страницы
     * @param Collection<int, MetaTagData> $meta   теги meta
     * @param Collection<int, LinkTagData> $link   теги link
     * @param Collection<int, BaseType>    $jsonLd разметка Schema.org
     */
    public function __construct(
        public string $title,
        public Collection $meta,
        public Collection $link,
        public Collection $jsonLd,
    ) {
    }

    /**
     * Теги для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'meta' => $this->meta->values()->toArray(),
            'link' => $this->link->values()->toArray(),
            'json_ld' => $this->jsonLd->map(static fn (BaseType $schema): array => $schema->toArray())->values()->all(),
        ];
    }
}
