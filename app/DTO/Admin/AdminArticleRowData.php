<?php

declare(strict_types=1);

namespace App\DTO\Admin;

use Illuminate\Support\Collection;

/**
 * Строка модерации статьи в админке.
 */
final readonly class AdminArticleRowData
{
    /**
     * @param int                              $id              идентификатор статьи
     * @param string                           $title           заголовок
     * @param string                           $excerpt         начало текста
     * @param string                           $authorName      имя автора или подпись удалённого автора
     * @param string                           $sections        разделы через разделитель или пустая строка
     * @param int                              $viewCount       число просмотров
     * @param string                           $status          текущее действие модерации
     * @param string|null                      $rejectionReason причина отклонения, если статья отклонена
     * @param string                           $editUrl         адрес формы правки
     * @param Collection<int, AdminOptionData> $statusOptions   варианты статуса для выбора
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $excerpt,
        public string $authorName,
        public string $sections,
        public int $viewCount,
        public string $status,
        public ?string $rejectionReason,
        public string $editUrl,
        public Collection $statusOptions,
    ) {
    }
}
