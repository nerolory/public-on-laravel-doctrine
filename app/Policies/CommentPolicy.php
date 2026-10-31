<?php

declare(strict_types=1);

namespace App\Policies;

use App\Entities\Comment;
use App\Entities\User;
use App\Services\User\PermissionService;
use App\Traits\AllowsOwnerOrModerator;
use Illuminate\Auth\Access\Response;

/**
 * Доступ к комментарию: ответы в ветке, правка, удаление и реакции.
 */
final class CommentPolicy
{
    use AllowsOwnerOrModerator;

    /**
     * @param PermissionService $permissions роли пользователя
     */
    public function __construct(
        private readonly PermissionService $permissions,
    ) {
    }

    /**
     * Ответы есть только у корневого комментария опубликованной статьи.
     */
    public function viewReplies(?User $user, Comment $comment): Response
    {
        return $comment->getParent() === null && $comment->getArticle()->isPublished()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Правит текст только автор незаблокированной учётной записи.
     */
    public function update(User $user, Comment $comment): Response
    {
        if (! $comment->isVisible()) {
            return Response::denyAsNotFound();
        }

        return $comment->isOwnedBy($user) && ! $user->isBanned() ? Response::allow() : Response::deny();
    }

    /**
     * Удаляет автор или модератор.
     */
    public function delete(User $user, Comment $comment): Response
    {
        if ($comment->isDeleted()) {
            return Response::denyAsNotFound();
        }

        return $this->isOwnerOrModerator($user, $comment, $this->permissions)
            ? Response::allow()
            : Response::deny();
    }

    /**
     * Ответ на видимый комментарий доступен незаблокированному пользователю.
     */
    public function reply(User $user, Comment $comment): Response
    {
        return $comment->isVisible() && ! $user->isBanned() ? Response::allow() : Response::deny();
    }

    /**
     * Реакция на видимый комментарий опубликованной статьи незаблокированным пользователем; на свой реагировать нельзя.
     */
    public function react(User $user, Comment $comment): Response
    {
        if (! $comment->isVisible() || ! $comment->getArticle()->isPublished()) {
            return Response::denyAsNotFound();
        }

        if ($user->isBanned()) {
            return Response::deny();
        }

        return $comment->isOwnedBy($user) ? Response::deny(__('messages.reactions.own')) : Response::allow();
    }
}
