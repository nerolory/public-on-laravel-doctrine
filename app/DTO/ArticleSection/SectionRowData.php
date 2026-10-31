<?php

declare(strict_types=1);

namespace App\DTO\ArticleSection;

/**
 * Строка списка разделов в админке: раздел, статьи и подразделы.
 */
final readonly class SectionRowData
{
    /**
     * @param SectionNodeData $node         раздел
     * @param int             $articleCount число статей в разделе
     * @param bool            $hasChildren  есть ли подразделы
     * @param bool            $deletable    можно ли удалить раздел
     */
    public function __construct(
        public SectionNodeData $node,
        public int $articleCount,
        public bool $hasChildren,
        public bool $deletable,
    ) {
    }
}
