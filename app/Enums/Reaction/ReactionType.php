<?php

declare(strict_types=1);

namespace App\Enums\Reaction;

use Illuminate\Support\Collection;

/** Три положительные и три отрицательные реакции. Пользователь может поставить несколько разных. */
enum ReactionType: string
{
    /** Нравится. */
    case Like = 'like';

    /** Восхищение. */
    case Love = 'love';

    /** Полезно или познавательно. */
    case Insightful = 'insightful';

    /** Не нравится. */
    case Dislike = 'dislike';

    /** Возмущение. */
    case Angry = 'angry';

    /** Скучно. */
    case Boring = 'boring';

    /**
     * Относится ли реакция к положительным.
     */
    public function isPositive(): bool
    {
        return self::positive()->contains($this);
    }

    /**
     * Положительные реакции.
     *
     * @return Collection<int, self>
     */
    public static function positive(): Collection
    {
        return collect([self::Like, self::Love, self::Insightful]);
    }

    /**
     * Отрицательные реакции.
     *
     * @return Collection<int, self>
     */
    public static function negative(): Collection
    {
        return collect([self::Dislike, self::Angry, self::Boring]);
    }
}
