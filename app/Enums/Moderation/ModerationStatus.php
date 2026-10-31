<?php

declare(strict_types=1);

namespace App\Enums\Moderation;

use InvalidArgumentException;

/**
 * Состояние модерации статьи и альбома.
 */
enum ModerationStatus: string
{
    /** Ожидает решения модератора. */
    case Pending = 'pending';

    /** Опубликован и виден посетителям. */
    case Published = 'published';

    /** Отклонён модератором, владелец видит причину. */
    case Rejected = 'rejected';

    /**
     * Наибольшая длина причины отклонения или снятия с публикации: 2000 символов вмещают объяснение автору
     * в несколько абзацев и ограничивают объём текста в колонке rejection_reason.
     */
    public const int REASON_LENGTH = 2000;

    /**
     * Причина без пробелов по краям; пустая причина не принимается.
     *
     * @throws InvalidArgumentException причина пустая
     */
    public static function normalizeReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        return $reason;
    }
}
