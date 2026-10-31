<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\User;

/**
 * Запросы, которые создают или меняют материалы: пользователь вошёл и не заблокирован.
 */
trait RequiresActiveUser
{
    /**
     * Заблокированный пользователь ничего не создаёт и не удаляет.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && ! $user->isBanned();
    }
}
