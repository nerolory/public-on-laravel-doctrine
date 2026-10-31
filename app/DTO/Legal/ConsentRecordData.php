<?php

declare(strict_types=1);

namespace App\DTO\Legal;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Запись журнала согласий для выгрузки данных пользователя.
 *
 * @implements Arrayable<string, int|string|bool>
 */
final readonly class ConsentRecordData implements Arrayable
{
    /**
     * @param string            $type      вид согласия
     * @param int               $version   версия документа
     * @param string            $title     заголовок документа
     * @param bool              $agreed    согласие дано (true) или отозвано
     * @param DateTimeImmutable $createdAt время записи
     */
    public function __construct(
        public string $type,
        public int $version,
        public string $title,
        public bool $agreed,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Запись для файла выгрузки.
     *
     * @return array{type: string, version: int, title: string, agreed: bool, created_at: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'version' => $this->version,
            'title' => $this->title,
            'agreed' => $this->agreed,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
