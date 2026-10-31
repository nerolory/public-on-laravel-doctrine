<?php

declare(strict_types=1);

namespace App\DTO\User;

/**
 * Число пользователей для главной страницы админки.
 */
final readonly class UserCountsData
{
    /**
     * @param int $total  пользователей без удалённых
     * @param int $banned из них заблокированных
     */
    public function __construct(
        public int $total,
        public int $banned,
    ) {
    }
}
