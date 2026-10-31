<?php

declare(strict_types=1);

namespace App\DTO\User;

/**
 * Имя и email из регистрации или смены адреса.
 */
final readonly class UserData
{
    /**
     * @param string|null $name  имя; null, если соцсеть его не передала
     * @param string      $email адрес почты
     */
    public function __construct(
        public ?string $name,
        public string $email,
    ) {
    }
}
