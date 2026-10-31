<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\User;

/**
 * Цель жалобы, которую скрывает любой модератор и которая скрывается автоматически по порогу жалоб.
 */
trait ModeratorHides
{
    /**
     * Скрыть объект может любой модератор.
     */
    public function canHide(object $entity, User $moderator): bool
    {
        return true;
    }

    /**
     * Подпись кнопки, которая скрывает материал с сайта.
     */
    public function hideLabel(): string
    {
        return __('admin.complaints.hide');
    }

    /**
     * Объект скрывается автоматически, когда жалоб набирается больше порога из настроек.
     */
    public function hidesAutomatically(): bool
    {
        return true;
    }
}
