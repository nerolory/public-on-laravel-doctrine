<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\Article;
use App\Entities\Comment;
use App\Entities\User;
use App\Enums\Reaction\ReactionType;
use App\Http\Requests\Comment\ListCommentsRequest;
use App\Http\Requests\Comment\SaveCommentRequest;
use App\Http\Requests\Common\PageRequest;
use App\Http\Requests\Reaction\ReactionRequest;
use App\Services\Comment\CommentService;
use App\Services\Reaction\ReactionService;
use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Комментарии и реакции. Читать могут все, писать и реагировать только зарегистрированные.
 * Доступ проверяют политики на маршрутах.
 */
final class CommentController extends Controller
{
    /**
     * @param ResponseFactory $responses  ответы
     * @param Redirector      $redirector перенаправления
     * @param CommentService  $comments   комментарии
     * @param ReactionService $reactions  реакции
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly CommentService $comments,
        private readonly ReactionService $reactions,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Ветки комментариев статьи.
     */
    public function index(Article $article, ListCommentsRequest $request): JsonResponse
    {
        return $this->responses->json(
            $this->comments->threads($article, $request->popularFirst(), $request->page(), $request->user()),
        );
    }

    /**
     * Ответы в ветке.
     */
    public function replies(Comment $comment, PageRequest $request): JsonResponse
    {
        return $this->responses->json($this->comments->replies($comment, $request->page(), $request->user()));
    }

    /**
     * Новый комментарий или ответ.
     */
    public function store(Article $article, SaveCommentRequest $request): JsonResponse
    {
        $comment = $this->comments->post(
            $article,
            $request->user(),
            $request->body(),
            replyToId: $request->replyToId(),
        );

        return $this->responses->json($comment, Response::HTTP_CREATED);
    }

    /**
     * Правка своего комментария.
     */
    public function update(Comment $comment, SaveCommentRequest $request): JsonResponse
    {
        return $this->responses->json($this->comments->edit($comment, $request->user(), $request->body()));
    }

    /**
     * Удаление комментария.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $this->comments->delete($comment);

        return $this->responses->json(['message' => __('messages.comments.deleted')]);
    }

    /**
     * Ставит (PUT) или снимает (DELETE) реакцию на статью.
     */
    public function reactToArticle(Article $article, ReactionRequest $request): JsonResponse
    {
        return $this->react(
            $request,
            fn (User $user, ReactionType $type, bool $on): mixed => $this->reactions->toggleArticle(
                $article,
                $user,
                $type,
                on: $on,
            ),
        );
    }

    /**
     * Ставит (PUT) или снимает (DELETE) реакцию на комментарий.
     */
    public function reactToComment(Comment $comment, ReactionRequest $request): JsonResponse
    {
        return $this->react(
            $request,
            fn (User $user, ReactionType $type, bool $on): mixed => $this->reactions->toggleComment(
                $comment,
                $user,
                $type,
                on: $on,
            ),
        );
    }

    /**
     * Ответ на постановку или снятие реакции: вызов сервиса получает пользователя, вид реакции и признак постановки.
     *
     * @param Closure(User, ReactionType, bool): mixed $toggle вызов сервиса реакций
     */
    private function react(ReactionRequest $request, Closure $toggle): JsonResponse
    {
        return $this->responses->json($toggle($request->user(), $request->type(), $request->on()));
    }
}
