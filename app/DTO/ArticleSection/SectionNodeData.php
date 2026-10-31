<?php

declare(strict_types=1);

namespace App\DTO\ArticleSection;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Раздел в дереве: уровень вложенности и адрес родителя.
 *
 * @implements Arrayable<string, int|string|null>
 */
final readonly class SectionNodeData implements Arrayable
{
    /** Отступ одного уровня в выпадающих списках. */
    private const string INDENT = '— ';

    /**
     * @param int         $id     идентификатор раздела
     * @param string      $name   название
     * @param string      $slug   адрес раздела
     * @param int         $level  уровень вложенности: 0 у корневых
     * @param string|null $parent адрес родителя
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $level,
        public ?string $parent,
    ) {
    }

    /**
     * Название с отступом по уровню вложенности.
     */
    public function indentedName(): string
    {
        return str_repeat(self::INDENT, $this->level) . $this->name;
    }

    /**
     * Раздел для ответа API.
     *
     * @return array{id: int, name: string, slug: string, level: int, parent: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'level' => $this->level,
            'parent' => $this->parent,
        ];
    }
}
