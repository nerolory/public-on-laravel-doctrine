<?php

declare(strict_types=1);

namespace App\Policies;

use App\Entities\Poll;
use App\Entities\User;
use Illuminate\Auth\Access\Response;

/**
 * Доступ к опросу статьи: голосование и список проголосовавших.
 */
final class PollPolicy
{
    /**
     * Голосуют в опросе опубликованной статьи.
     */
    public function vote(User $user, Poll $poll): Response
    {
        return $poll->getArticle()->isPublished() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Проголосовавших видно только в неанонимном опросе опубликованной статьи.
     */
    public function viewVoters(?User $user, Poll $poll): Response
    {
        return ! $poll->isAnonymous() && $poll->getArticle()->isPublished()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
