<?php

declare(strict_types=1);

namespace App\Events\User;

/**
 * Пользователь сменил адрес почты; новый адрес нужно подтвердить.
 */
final readonly class EmailChanged
{
    /**
     * @param int    $userId идентификатор пользователя
     * @param string $locale язык сайта при смене адреса: на нём уходит письмо
     */
    public function __construct(
        public int $userId,
        public string $locale,
    ) {
    }
}
