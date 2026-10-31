<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\Article;
use App\Http\Requests\Article\ListArticlesRequest;
use App\Http\Requests\Article\StoreArticleRequest;
use App\Http\Requests\Article\SuggestTagsRequest;
use App\Services\Article\ArticleEditorService;
use App\Services\Article\ArticleFeedService;
use App\Services\Article\ArticlePageService;
use App\Services\Article\ArticleViewService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Статьи на сайте: лента, страница статьи, просмотры, подсказки тегов и новая статья.
 */
final class ArticleController extends Controller
{
    /**
     * @param ResponseFactory      $responses  ответы
     * @param Redirector           $redirector перенаправления
     * @param ArticleFeedService   $feed       лента и подсказки тегов
     * @param ArticlePageService   $pages      страница статьи
     * @param ArticleViewService   $views      учёт просмотров
     * @param ArticleEditorService $editor     создание статей
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ArticleFeedService $feed,
        private readonly ArticlePageService $pages,
        private readonly ArticleViewService $views,
        private readonly ArticleEditorService $editor,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Лента опубликованных статей с фильтром по разделу.
     */
    public function index(ListArticlesRequest $request): JsonResponse
    {
        return $this->responses->json($this->feed->page($request->page(), $request->section()));
    }

    /**
     * Страница статьи.
     */
    public function show(Article $article, Request $request): JsonResponse
    {
        return $this->responses->json($this->pages->show($article, $request->user()));
    }

    /**
     * Просмотр статьи: страница отправляет его после загрузки, боты отсеиваются на сервере.
     */
    public function view(Article $article, Request $request): Response
    {
        $this->views->record(
            $article,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
            $request->headers->get('referer'),
        );

        return $this->responses->noContent();
    }

    /**
     * Подсказки для поля тегов.
     */
    public function tags(SuggestTagsRequest $request): JsonResponse
    {
        return $this->responses->json(['items' => $this->feed->suggestTags($request->prefix())]);
    }

    /**
     * Новая статья автора уходит на модерацию.
     */
    public function store(StoreArticleRequest $request): JsonResponse
    {
        $this->editor->create($request->articleData(), $request->user());

        return $this->responses->json(['message' => __('messages.articles.submitted')], Response::HTTP_CREATED);
    }
}
