<?php

declare(strict_types=1);

namespace App\Enums\Comment;

/**
 * Состояние комментария: видимый, удалённый или скрытый.
 */
enum CommentState: string
{
    /** Текст и автор показываются посетителям. */
    case Visible = 'visible';

    /** Удалён: текст и автор не передаются. */
    case Deleted = 'deleted';

    /** Скрыт модерацией: текст и автор не передаются. */
    case Hidden = 'hidden';

    /**
     * Состояние комментария по флагам: удалённый комментарий важнее скрытого.
     */
    public static function fromFlags(bool $deleted, bool $hidden): self
    {
        return match (true) {
            $deleted => self::Deleted,
            $hidden => self::Hidden,
            default => self::Visible,
        };
    }
}
