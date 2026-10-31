<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\Article;
use App\Enums\Admin\AdminPage;
use App\Http\Requests\Article\ModerateArticlesRequest;
use App\Http\Requests\Article\StoreArticleRequest;
use App\Services\Article\ArticleEditorService;
use App\Services\Article\ArticleModerationService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Статьи в админке: создание, правка модератором, публикация, удаление и модерация списком.
 */
final class AdminArticleController extends Controller
{
    /**
     * @param ResponseFactory          $responses  ответы
     * @param Redirector               $redirector перенаправления
     * @param ArticleEditorService     $editor     создание и правка статей
     * @param ArticleModerationService $moderation смена состояния статей
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ArticleEditorService $editor,
        private readonly ArticleModerationService $moderation,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Новая статья из админки.
     */
    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $this->editor->create($request->articleData(), $request->user());

        return $this->backToAdminPage(AdminPage::Articles, __('admin.articles.flash.created'));
    }

    /**
     * Правка статьи модератором.
     */
    public function update(Article $article, StoreArticleRequest $request): RedirectResponse
    {
        $this->editor->update($article, $request->articleData(), $request->version(), $request->user());

        return $this->backToAdminPage(AdminPage::Articles, __('admin.articles.flash.updated'));
    }

    /**
     * Удаление статьи модератором.
     */
    public function destroy(Article $article): RedirectResponse
    {
        $this->moderation->delete($article);

        return $this->backToAdminPage(AdminPage::Articles, __('admin.articles.flash.deleted'));
    }

    /**
     * Публикация статьи.
     */
    public function publish(Article $article): RedirectResponse
    {
        $this->moderation->publish($article);

        return $this->backToAdminPage(AdminPage::Articles, __('admin.articles.flash.published'));
    }

    /**
     * Действие модератора над выбранными статьями.
     */
    public function moderate(ModerateArticlesRequest $request): RedirectResponse
    {
        $this->moderation->moderate($request->ids(), $request->action(), $request->reason());

        return $this->backToAdminPage(AdminPage::Articles, __('admin.articles.flash.moderated'));
    }
}
