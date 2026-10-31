<?php

declare(strict_types=1);

namespace App\Enums\Poll;

/**
 * Причина, по которой нельзя проголосовать или отозвать голос. Значение — ключ сообщения в messages.polls.
 */
enum PollProblem: string
{
    /** Голосование завершено. */
    case Closed = 'closed';

    /** Выбрано недопустимое число вариантов или чужой вариант. */
    case InvalidChoice = 'invalid_choice';

    /** Голос уже отдан, а переголосование выключено. */
    case AlreadyVoted = 'already_voted';

    /** Отзыв голоса выключен настройками опроса. */
    case RevoteDisabled = 'revote_disabled';
}
