<?php

declare(strict_types=1);

namespace App\Enums\Complaint;

/**
 * Решение модератора по объекту из очереди жалоб.
 */
enum ComplaintAction: string
{
    /** Скрыть объект; пользователя при этом блокируют. */
    case Hide = 'hide';

    /** Отклонить жалобы без скрытия объекта. */
    case Dismiss = 'dismiss';

    /**
     * Статус жалоб, который записывает решение: скрытие подтверждает жалобы, отклонение их снимает.
     */
    public function decision(): ComplaintStatus
    {
        return match ($this) {
            self::Hide => ComplaintStatus::Upheld,
            self::Dismiss => ComplaintStatus::Dismissed,
        };
    }
}
