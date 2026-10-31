<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Строка дерева разделов статей в админке.
 */
final readonly class AdminSectionRowData
{
    /**
     * @param int    $id            идентификатор раздела
     * @param string $name          название раздела
     * @param string $slug          адрес раздела
     * @param string $displayName   название с отметкой вложенности
     * @param string $levelClass    класс отступа по уровню вложенности
     * @param int    $articleCount  число статей в разделе
     * @param bool   $deletable     можно ли удалить раздел
     * @param string $editUrl       адрес формы правки
     * @param string $deleteTitle   подсказка кнопки удаления
     * @param string $deleteConfirm текст подтверждения удаления
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $displayName,
        public string $levelClass,
        public int $articleCount,
        public bool $deletable,
        public string $editUrl,
        public string $deleteTitle,
        public string $deleteConfirm,
    ) {
    }
}
