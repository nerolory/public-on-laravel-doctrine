<?php

declare(strict_types=1);

namespace App\DTO\Complaint;

/**
 * Подпись объекта жалобы для очереди модерации.
 * Ссылка на объект на сайте.
 */
final readonly class ComplaintLabelData
{
    /** Подпись объекта, которого уже нет. */
    public const string MISSING_TEXT = '—';

    /**
     * @param string      $text подпись: заголовок, начало текста или имя
     * @param string|null $url  адрес на сайте, если объект найден
     */
    public function __construct(
        public string $text,
        public ?string $url = null,
    ) {
    }
}
