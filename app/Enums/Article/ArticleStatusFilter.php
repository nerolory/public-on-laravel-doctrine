<?php

declare(strict_types=1);

namespace App\Enums\Article;

/**
 * Фильтр «Моих статей» по состоянию. Архивные статьи скрыты мягким удалением и в фильтре не участвуют.
 */
enum ArticleStatusFilter: string
{
    /** Ждут решения модератора. */
    case Pending = 'pending';

    /** Опубликованы. */
    case Published = 'published';

    /** Отклонены модератором. */
    case Rejected = 'rejected';
}
