<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\ArticleSection;
use App\Enums\Admin\AdminPage;
use App\Http\Requests\ArticleSection\DeleteArticleSectionRequest;
use App\Http\Requests\ArticleSection\StoreArticleSectionRequest;
use App\Http\Requests\Common\SectionFilterRequest;
use App\Services\Article\ArticleSectionService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Разделы статей: список и ряды фильтра для сайта, создание, правка и удаление в админке.
 */
final class ArticleSectionController extends Controller
{
    /**
     * @param ResponseFactory       $responses  ответы
     * @param Redirector            $redirector перенаправления
     * @param ArticleSectionService $sections   разделы
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ArticleSectionService $sections,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Все разделы деревом.
     */
    public function index(): JsonResponse
    {
        return $this->responses->json(['items' => $this->sections->all()]);
    }

    /**
     * Ряды кнопок фильтра для главной: строит сервер, фронтенд только выводит.
     */
    public function filter(SectionFilterRequest $request): JsonResponse
    {
        return $this->responses->json(['rows' => $this->sections->filterRows($request->section())]);
    }

    /**
     * Новый раздел.
     */
    public function store(StoreArticleSectionRequest $request): RedirectResponse
    {
        $this->sections->create($request->name(), $request->parentId());

        return $this->backToAdminPage(AdminPage::ArticleSections, __('admin.sections.flash.created'));
    }

    /**
     * Правка раздела.
     */
    public function update(ArticleSection $section, StoreArticleSectionRequest $request): RedirectResponse
    {
        $this->sections->update(
            $section,
            $request->name(),
            $request->slug(),
            $request->parentId(),
            $request->seoMeta(),
        );

        return $this->backToAdminPage(AdminPage::ArticleSections, __('admin.sections.flash.updated'));
    }

    /**
     * Удаление раздела; непустой корневой раздел возвращает к списку с ошибкой.
     */
    public function destroy(ArticleSection $section, DeleteArticleSectionRequest $request): RedirectResponse
    {
        $this->sections->delete($section);

        return $this->backToAdminPage(AdminPage::ArticleSections, __('admin.sections.flash.deleted'));
    }
}
