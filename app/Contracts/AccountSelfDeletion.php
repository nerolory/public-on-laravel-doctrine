<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Entities\User;
use App\Enums\Legal\ConsentType;
use Illuminate\Support\Collection;

/**
 * Удаление пользователем своей учётной записи.
 */
interface AccountSelfDeletion
{
    /**
     * Может ли пользователь удалить свою учётную запись.
     */
    public function canDeleteOwnAccount(User $user): bool;

    /**
     * Удаляет учётную запись и отзывает выбранные согласия; право на удаление проверяет UserPolicy.
     *
     * @param Collection<int, ConsentType> $revokedConsents согласия, которые пользователь отзывает вместе с удалением
     */
    public function deleteOwnAccount(User $user, Collection $revokedConsents, ?string $ip, ?string $userAgent): void;
}
