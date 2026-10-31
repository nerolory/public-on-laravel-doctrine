<?php

declare(strict_types=1);

namespace App\Enums\Complaint;

/**
 * Причины жалобы.
 */
enum ComplaintReason: string
{
    /** Оскорбление. */
    case Insult = 'insult';

    /** Разжигание ненависти. */
    case Hate = 'hate';

    /** Запрещённый контент. */
    case Illegal = 'illegal';

    /** Контент 18+ без пометки. */
    case Adult = 'adult';

    /** Нарушение авторских прав. */
    case Copyright = 'copyright';

    /** Иная причина, требует пояснения. */
    case Other = 'other';

    /**
     * Нужен ли текст жалобы: причина «другое» требует описания от жалующегося.
     */
    public function requiresText(): bool
    {
        return $this === self::Other;
    }
}
