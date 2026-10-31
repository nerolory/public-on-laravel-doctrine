<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\User;

/**
 * Запись, у которой есть пользователь-владелец: автор статьи или комментария, загрузивший картинку и т. п.
 */
trait HasOwner
{
    /**
     * Принадлежит ли запись пользователю.
     */
    public function isOwnedBy(User $user): bool
    {
        return $user->is($this->owner());
    }

    /**
     * Владелец записи; null — пользователь удалён.
     */
    abstract private function owner(): ?User;
}
