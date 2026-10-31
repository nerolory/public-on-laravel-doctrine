<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\Article;
use App\Http\Requests\Article\ArchiveArticleRequest;
use App\Http\Requests\Article\ListMyArticlesRequest;
use App\Http\Requests\Article\StoreArticleRequest;
use App\Services\Article\ArticleEditorService;
use App\Services\Article\ArticleFeedService;
use App\Services\Article\ArticleModerationService;
use App\Services\Article\ArticlePageService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;

/**
 * «Мои статьи»: список, правка и удаление в архив своих статей. Доступ к статье проверяет политика на маршруте.
 */
final class MyArticleController extends Controller
{
    /**
     * @param ResponseFactory          $responses  ответы
     * @param Redirector               $redirector перенаправления
     * @param ArticleFeedService       $feed       лента автора
     * @param ArticlePageService       $pages      форма правки статьи
     * @param ArticleEditorService     $editor     правка статей
     * @param ArticleModerationService $moderation архивация статей
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ArticleFeedService $feed,
        private readonly ArticlePageService $pages,
        private readonly ArticleEditorService $editor,
        private readonly ArticleModerationService $moderation,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Статьи автора с фильтром по состоянию.
     */
    public function index(ListMyArticlesRequest $request): JsonResponse
    {
        return $this->responses->json($this->feed->authorPage($request->user(), $request->page(), $request->status()));
    }

    /**
     * Своя статья для формы правки.
     */
    public function edit(Article $article): JsonResponse
    {
        return $this->responses->json($this->pages->editForm($article));
    }

    /**
     * Правка своей статьи; статья возвращается на модерацию.
     */
    public function update(Article $article, StoreArticleRequest $request): JsonResponse
    {
        $this->editor->revise($article, $request->articleData(), $request->version());

        return $this->responses->json(['message' => __('messages.articles.revised')]);
    }

    /**
     * Удаление своей статьи в архив с причиной.
     */
    public function destroy(Article $article, ArchiveArticleRequest $request): JsonResponse
    {
        $this->moderation->archive($article, $request->reason());

        return $this->responses->json(['message' => __('messages.articles.archived')]);
    }
}
