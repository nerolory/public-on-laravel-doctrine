<?php

declare(strict_types=1);

namespace App\Policies;

use App\Entities\Chart;
use App\Entities\User;
use App\Services\User\PermissionService;
use App\Traits\AllowsOwnerOrModerator;
use Illuminate\Auth\Access\Response;

/**
 * Доступ к данным графика для правки в редакторе.
 */
final class ChartPolicy
{
    use AllowsOwnerOrModerator;

    /**
     * @param PermissionService $permissions роли пользователя
     */
    public function __construct(
        private readonly PermissionService $permissions,
    ) {
    }

    /**
     * Таблицу графика открывают владелец и модераторы; для остальных графика нет.
     */
    public function view(User $user, Chart $chart): Response
    {
        return $this->isOwnerOrModerator($user, $chart, $this->permissions)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
