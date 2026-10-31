<?php

declare(strict_types=1);

namespace App\Policies;

use App\Entities\Article;
use App\Entities\User;
use App\Services\User\PermissionService;
use App\Traits\AllowsOwnerOrModerator;
use Illuminate\Auth\Access\Response;

/**
 * Доступ к статье: чтение, работа автора и участие читателей.
 */
final class ArticlePolicy
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
     * Опубликованную статью видят все, неопубликованную — только автор и модераторы.
     */
    public function view(?User $user, Article $article): Response
    {
        if ($article->isPublished()) {
            return Response::allow();
        }

        return $user !== null && $this->isOwnerOrModerator($user, $article, $this->permissions)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Комментарии, просмотры и опрос есть только у опубликованной статьи.
     */
    public function interact(?User $user, Article $article): Response
    {
        return $article->isPublished() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Реакция на опубликованную статью; на свою реагировать нельзя.
     */
    public function react(User $user, Article $article): Response
    {
        if (! $article->isPublished()) {
            return Response::denyAsNotFound();
        }

        return $article->isOwnedBy($user) ? Response::deny(__('messages.reactions.own')) : Response::allow();
    }

    /**
     * Правка и удаление в архив своей статьи; чужая статья для автора не существует.
     */
    public function revise(User $user, Article $article): Response
    {
        return $article->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
