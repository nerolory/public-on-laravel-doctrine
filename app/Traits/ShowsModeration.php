<?php

declare(strict_types=1);

namespace App\Traits;

use App\DTO\Admin\AdminOptionData;
use App\Enums\Moderation\ModerationAction;
use Illuminate\Support\Collection;

/**
 * Списки модерации статей и альбомов: варианты статуса, имя автора и параметры диалога причины отклонения.
 */
trait ShowsModeration
{
    /** Высота поля причины отклонения, строк. */
    private const int REJECT_REASON_ROWS = 4;

    /**
     * Варианты статуса для выбора в строке; текущий статус отмечен.
     *
     * @return Collection<int, AdminOptionData>
     */
    protected function statusOptions(string $current): Collection
    {
        return ModerationAction::statusChanges()->map(
            static fn (ModerationAction $option): AdminOptionData => new AdminOptionData(
                value: $option->value,
                label: __('admin.articles.statuses.' . $option->value),
                selected: $option->value === $current,
            ),
        );
    }

    /**
     * Имя автора в списке; у удалённого пользователя — подпись «удалённый пользователь».
     */
    protected function displayAuthorName(?string $name): string
    {
        return $name === null ? __('admin.articles.deleted_author') : $name;
    }
}
