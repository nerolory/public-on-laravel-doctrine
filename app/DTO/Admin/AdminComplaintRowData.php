<?php

declare(strict_types=1);

namespace App\DTO\Admin;

use Illuminate\Support\Collection;

/**
 * Группа открытых жалоб на один объект в очереди модерации.
 */
final readonly class AdminComplaintRowData
{
    /**
     * @param int                     $id          идентификатор объекта жалобы
     * @param string                  $target      вид объекта: article, comment, album, user
     * @param int                     $total       число открытых жалоб
     * @param string                  $labelText   подпись объекта
     * @param string|null             $labelUrl    публичный адрес объекта или null
     * @param Collection<int, string> $texts       тексты, приложенные к жалобам
     * @param Collection<int, string> $reasonLines строки «причина: число» по причинам жалоб
     * @param string                  $hideLabel   подпись кнопки скрытия или блокировки
     */
    public function __construct(
        public int $id,
        public string $target,
        public int $total,
        public string $labelText,
        public ?string $labelUrl,
        public Collection $texts,
        public Collection $reasonLines,
        public string $hideLabel,
    ) {
    }
}
