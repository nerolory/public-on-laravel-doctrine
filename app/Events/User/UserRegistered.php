<?php

declare(strict_types=1);

namespace App\Events\User;

/**
 * Пользователь зарегистрировался. Слушатель ставит письмо подтверждения в очередь по идентификатору и языку из события.
 */
final readonly class UserRegistered
{
    /**
     * @param int    $userId идентификатор пользователя
     * @param string $locale язык сайта при регистрации: на нём уходит письмо
     */
    public function __construct(
        public int $userId,
        public string $locale,
    ) {
    }
}
