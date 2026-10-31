<?php

declare(strict_types=1);

namespace App\Enums\Article;

use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationStatus;

/**
 * Состояние статьи: на модерации, опубликована, отклонена или удалена автором в архив.
 */
enum ArticleStatus: string
{
    /** Ожидает решения модератора. */
    case Pending = 'pending';

    /** Опубликована и видна посетителям. */
    case Published = 'published';

    /** Отклонена модератором, автор видит причину. */
    case Rejected = 'rejected';

    /** В архиве после мягкого удаления. */
    case Archived = 'archived';

    /**
     * Состояние неархивной статьи по флагам публикации и отклонения.
     */
    public static function fromFlags(bool $published, bool $rejected): self
    {
        return match (true) {
            $published => self::Published,
            $rejected => self::Rejected,
            default => self::Pending,
        };
    }

    /**
     * Действие модерации, которое приводит статью в это состояние: выбранный пункт в списке статуса.
     */
    public function moderationAction(): ModerationAction
    {
        return ModerationAction::leadingTo(ModerationStatus::from($this->value));
    }
}
