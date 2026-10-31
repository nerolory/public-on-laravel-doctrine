<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Скрытие статьи или альбома по жалобам: материал отклоняется с пометкой о жалобах.
 */
trait HidesByRejection
{
    /**
     * Отклоняет материал с пометкой о жалобах, чтобы он пропал с сайта.
     */
    public function hide(object $entity): void
    {
        $entity->reject(__('messages.complaints.hidden_reason'));
    }
}
