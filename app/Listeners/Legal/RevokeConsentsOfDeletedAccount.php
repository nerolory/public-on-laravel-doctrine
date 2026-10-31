<?php

declare(strict_types=1);

namespace App\Listeners\Legal;

use App\Events\User\AccountDeleted;
use App\Services\Legal\ConsentService;

/**
 * Записывает в журнал отзыв согласий, которые пользователь выбрал при удалении учётной записи.
 */
final class RevokeConsentsOfDeletedAccount
{
    /**
     * @param ConsentService $consents согласия
     */
    public function __construct(
        private readonly ConsentService $consents,
    ) {
    }

    /**
     * Отзыв любых согласий, в том числе обязательных: учётная запись уже удалена.
     */
    public function handle(AccountDeleted $event): void
    {
        $this->consents->revoke(
            $event->user,
            $event->revokedConsents,
            $event->ip,
            $event->userAgent,
            deletingAccount: true,
        );
    }
}
