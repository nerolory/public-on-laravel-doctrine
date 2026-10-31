<?php

declare(strict_types=1);

namespace App\DTO\ArticleSection;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Ряд кнопок фильтра на главной: разделы одного уровня.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SectionFilterRowData implements Arrayable
{
    /**
     * @param string                           $key      ключ ряда: root или адрес родителя
     * @param string|null                      $all      адрес для кнопки «все»; null — без фильтра по разделу
     * @param string                           $allLabel ключ подписи: all или whole_section
     * @param string|null                      $active   адрес выбранного раздела в ряду; null — ничего не выбрано
     * @param Collection<int, SectionNodeData> $items    разделы ряда
     */
    public function __construct(
        public string $key,
        public ?string $all,
        public string $allLabel,
        public ?string $active,
        public Collection $items,
    ) {
    }

    /**
     * Ряд для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'all' => $this->all,
            'all_label' => $this->allLabel,
            'active' => $this->active,
            // Адрес и название каждого раздела ряда.
            'items' => $this->items
                ->map(static fn (SectionNodeData $node): array => ['slug' => $node->slug, 'name' => $node->name])
                ->values()
                ->all(),
        ];
    }
}
