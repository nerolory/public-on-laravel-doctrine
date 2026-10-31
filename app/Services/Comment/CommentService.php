<?php

declare(strict_types=1);

namespace App\Services\Comment;

use App\DTO\Comment\CommentData;
use App\DTO\Comment\CommentPermissionsData;
use App\DTO\Common\PageData;
use App\Entities\Article;
use App\Entities\Comment;
use App\Entities\User;
use App\Enums\Comment\CommentState;
use App\Repositories\CommentRepository;
use App\Services\Reaction\ReactionService;
use App\Services\User\UserService;
use App\Support\Settings\PageSizes;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Collection;

/**
 * Комментарии к опубликованным статьям: ветки с ответами одним уровнем, сортировка «популярные» или «новые».
 * Писать могут только зарегистрированные; правит автор, удаляет автор или модератор.
 */
final class CommentService
{
    /**
     * @param CommentRepository $comments  комментарии
     * @param ReactionService   $reactions реакции на комментарии
     * @param UserService       $users     карточки авторов
     * @param PageSizes         $pageSizes размеры списков из настроек
     * @param Gate              $gate      права на действия с комментарием по политике
     */
    public function __construct(
        private readonly CommentRepository $comments,
        private readonly ReactionService $reactions,
        private readonly UserService $users,
        private readonly PageSizes $pageSizes,
        private readonly Gate $gate,
    ) {
    }

    /**
     * Ветки статьи и число видимых комментариев.
     *
     * @return PageData<CommentData>
     */
    public function threads(Article $article, bool $popularFirst, int $page, ?User $viewer): PageData
    {
        $threads = $this->comments->threads($article, $popularFirst, $page, $this->pageSizes->comments());

        return new PageData(
            items: $this->present($threads->items, $viewer),
            hasMore: $threads->hasMore,
            total: $this->comments->countVisible($article),
        );
    }

    /**
     * Ответы в ветке корневого комментария.
     *
     * @return PageData<CommentData>
     */
    public function replies(Comment $root, int $page, ?User $viewer): PageData
    {
        $replies = $this->comments->replies($root, $page, $this->pageSizes->comments());

        return new PageData($this->present($replies->items, $viewer), $replies->hasMore);
    }

    /**
     * Новый комментарий или ответ на видимый комментарий той же статьи.
     */
    public function post(Article $article, User $author, string $body, ?int $replyToId): CommentData
    {
        $repliedTo = $replyToId === null ? null : $this->repliedTo($article, $replyToId);
        $comment = Comment::post($article, $author, $body, $repliedTo);
        $this->comments->save($comment);

        return $this->present(collect([$comment]), $author)->first();
    }

    /**
     * Правка текста автором.
     */
    public function edit(Comment $comment, User $user, string $body): CommentData
    {
        $comment->edit($body);
        $this->comments->flush();

        return $this->present(collect([$comment]), $user)->first();
    }

    /**
     * Удаление автором или модератором.
     */
    public function delete(Comment $comment): void
    {
        $comment->delete();
        $this->comments->flush();
    }

    /**
     * Комментарий той же статьи, на который можно ответить, или null.
     */
    public function replyTarget(Article $article, int $id): ?Comment
    {
        $comment = $this->comments->find($id);

        return $comment !== null && $comment->isVisible() && $comment->getArticle()->getId() === $article->getId()
            ? $comment
            : null;
    }

    /**
     * Комментарий, на который отвечают; если он стал недоступен после проверки запроса, это 404.
     */
    private function repliedTo(Article $article, int $replyToId): Comment
    {
        return $this->replyTarget($article, $replyToId)
            ?? throw EntityNotFoundException::fromClassNameAndIdentifier(Comment::class, ['id' => $replyToId]);
    }

    /**
     * Комментарии для ответа API: реакции загружаются одним запросом на всю страницу.
     *
     * @param Collection<int, Comment> $comments
     *
     * @return Collection<int, CommentData>
     */
    private function present(Collection $comments, ?User $viewer): Collection
    {
        $reactions = $this->reactions->commentSummaries($comments, $viewer);

        return $comments->map(fn (Comment $comment): CommentData => new CommentData(
            id: $comment->persistedId(),
            parentId: $comment->getParent()?->getId(),
            body: $comment->isVisible() ? $comment->getBody() : null,
            state: $this->state($comment),
            author: $comment->isVisible() ? $this->users->card($comment->getAuthor()) : null,
            replyTo: $comment->getReplyTo() === null ? null : $this->users->card($comment->getReplyTo()),
            createdAt: $comment->getCreatedAt(),
            edited: $comment->getEditedAt() !== null,
            replyCount: $comment->getReplyCount(),
            reactions: $reactions->get($comment->persistedId()),
            permissions: $this->permissionsFor($comment, $viewer),
        ))->values();
    }

    /**
     * Действия посетителя по политике комментария; гостю недоступно ничего.
     */
    private function permissionsFor(Comment $comment, ?User $viewer): CommentPermissionsData
    {
        $gate = $this->gate->forUser($viewer);

        return new CommentPermissionsData(
            edit: $gate->allows('update', $comment),
            delete: $gate->allows('delete', $comment),
            reply: $gate->allows('reply', $comment),
            react: $gate->allows('react', $comment),
        );
    }

    /**
     * Состояние комментария для ответа API.
     */
    private function state(Comment $comment): CommentState
    {
        return CommentState::fromFlags(deleted: $comment->isDeleted(), hidden: $comment->isHidden());
    }
}
