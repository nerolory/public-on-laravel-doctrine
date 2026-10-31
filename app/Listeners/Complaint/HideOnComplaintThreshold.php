<?php

declare(strict_types=1);

namespace App\Listeners\Complaint;

use App\Enums\Settings\SettingKey;
use App\Events\Complaint\ComplaintFiled;
use App\Repositories\ComplaintRepository;
use App\Services\Complaint\ComplaintService;
use App\Services\Complaint\ComplaintTargets;
use App\Services\Settings\SettingsService;

/**
 * Скрывает объект, когда открытых жалоб набирается не меньше порога. Работает, только если администратор
 * включил автоскрытие; пользователей не блокирует.
 */
final class HideOnComplaintThreshold
{
    /**
     * @param ComplaintTargets    $targets    типы объектов жалоб
     * @param ComplaintRepository $complaints жалобы
     * @param ComplaintService    $moderation решение по жалобам
     * @param SettingsService     $settings   настройки автоскрытия
     */
    public function __construct(
        private readonly ComplaintTargets $targets,
        private readonly ComplaintRepository $complaints,
        private readonly ComplaintService $moderation,
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * Проверяет порог после новой жалобы.
     */
    public function handle(ComplaintFiled $event): void
    {
        $target = $this->targets->get($event->target);
        if (! $target->hidesAutomatically() || ! $this->settings->boolean(SettingKey::ContentComplaintsAutoHide)) {
            return;
        }
        $threshold = $this->settings->integer(SettingKey::ContentComplaintsThreshold);
        if ($this->complaints->countOpen($target, $event->entity) < $threshold) {
            return;
        }
        $this->moderation->uphold($target, $event->entity, moderator: null);
    }
}
