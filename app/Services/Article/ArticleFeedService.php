<?php

declare(strict_types=1);

namespace App\Services\Article;

use App\DTO\Article\ArticleListItemData;
use App\DTO\Article\ArticleModerationRowData;
use App\DTO\Article\MyArticlesPageData;
use App\DTO\Common\PageData;
use App\Entities\ArticleSection;
use App\Entities\User;
use App\Enums\Article\ArticleStatusFilter;
use App\Repositories\ArticleListRepository;
use App\Repositories\ArticleSectionRepository;
use App\Repositories\TagRepository;
use App\Support\Settings\PageSizes;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Support\Collection;

/**
 * Списки статей: лента с фильтром по разделу, «Мои статьи», модерация и подсказки тегов.
 */
final class ArticleFeedService
{
    /**
     * @param ArticleListRepository    $lists     списки статей
     * @param ArticleSectionRepository $sections  разделы
     * @param TagRepository            $tags      теги
     * @param PageSizes                $pageSizes размеры списков из настроек
     */
    public function __construct(
        private readonly ArticleListRepository $lists,
        private readonly ArticleSectionRepository $sections,
        private readonly TagRepository $tags,
        private readonly PageSizes $pageSizes,
    ) {
    }

    /**
     * Лента опубликованных статей; с разделом — статьи раздела и его подразделов.
     *
     * @return PageData<ArticleListItemData>
     *
     * @throws EntityNotFoundException раздела с таким адресом нет
     */
    public function page(int $page, ?string $sectionSlug = null): PageData
    {
        if ($sectionSlug === null) {
            return $this->lists->publishedPage($page, $this->pageSizes->articles());
        }

        $section = $this->sections->findBySlug($sectionSlug);

        if ($section === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(ArticleSection::class, ['slug' => $sectionSlug]);
        }

        return $this->lists->publishedPage(
            $page,
            $this->pageSizes->articles(),
            sectionIds: $this->sections->subtreeIds($section),
        );
    }

    /**
     * «Мои статьи»: страница статей автора с фильтром по состоянию и число статей по состояниям.
     */
    public function authorPage(User $author, int $page, ?ArticleStatusFilter $status = null): MyArticlesPageData
    {
        return new MyArticlesPageData(
            page: $this->lists->authorPage($author, $page, $this->pageSizes->articles(), $status),
            counts: $this->lists->authorCounts($author),
        );
    }

    /**
     * Страница списка модерации в админке.
     *
     * @return PageData<ArticleModerationRowData>
     */
    public function moderationPage(int $page): PageData
    {
        return $this->lists->moderationPage($page, $this->pageSizes->admin());
    }

    /**
     * Подсказки для поля тегов.
     *
     * @return Collection<int, string>
     */
    public function suggestTags(string $prefix): Collection
    {
        return $this->tags->suggest($prefix, $this->pageSizes->tagSuggestions());
    }
}
