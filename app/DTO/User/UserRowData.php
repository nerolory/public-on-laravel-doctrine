<?php

declare(strict_types=1);

namespace App\DTO\User;

use DateTimeImmutable;

/**
 * Строка списка пользователей в админке.
 */
final readonly class UserRowData
{
    /**
     * @param int                    $id       идентификатор пользователя
     * @param string                 $name     полное имя или часть email
     * @param string                 $email    адрес почты
     * @param DateTimeImmutable|null $bannedAt дата блокировки
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?DateTimeImmutable $bannedAt,
    ) {
    }
}
