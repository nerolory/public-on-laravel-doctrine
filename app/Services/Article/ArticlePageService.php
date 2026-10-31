<?php

declare(strict_types=1);

namespace App\Services\Article;

use App\DTO\Article\ArticleEditData;
use App\DTO\Article\ArticlePermissionsData;
use App\DTO\Article\ArticleViewData;
use App\DTO\Media\ImageData;
use App\DTO\User\UserCardData;
use App\Entities\Article;
use App\Entities\ArticleSection;
use App\Entities\Image;
use App\Entities\Tag;
use App\Entities\User;
use App\Enums\Media\ImagePurpose;
use App\Repositories\ArticleRepository;
use App\Services\Media\ChartService;
use App\Services\Media\ImageService;
use App\Services\Poll\PollService;
use App\Services\Reaction\ReactionService;
use App\Services\Seo\SeoService;
use App\Services\Seo\ShareService;
use App\Services\User\PermissionService;
use App\Support\Seo\SiteUrls;
use Illuminate\Support\Collection;

/**
 * Страница статьи для посетителя и статья для формы правки.
 */
final class ArticlePageService
{
    /**
     * @param ArticleRepository $articles    статьи
     * @param ArticleContent    $content     HTML статьи
     * @param ImageService      $images      адреса и размеры картинок
     * @param PermissionService $permissions права модератора
     * @param PollService       $polls       опросы
     * @param ReactionService   $reactions   реакции
     * @param SeoService        $seo         метатеги страницы
     * @param ShareService      $share       ссылки «поделиться»
     * @param ChartService      $charts      данные графиков
     */
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ArticleContent $content,
        private readonly ImageService $images,
        private readonly PermissionService $permissions,
        private readonly PollService $polls,
        private readonly ReactionService $reactions,
        private readonly SeoService $seo,
        private readonly ShareService $share,
        private readonly ChartService $charts,
    ) {
    }

    /**
     * Страница статьи; кто её видит, решает ArticlePolicy. Причина отклонения видна автору и модераторам.
     */
    public function show(Article $article, ?User $viewer): ArticleViewData
    {
        $isAuthor = $viewer !== null && $article->isOwnedBy($viewer);
        $canModerate = $viewer !== null && $this->permissions->canModerate($viewer);
        $author = $this->articles->activeAuthor($article);
        $sections = self::sectionNames($article);
        $tags = self::tagNames($article);
        $path = SiteUrls::articlePath($article->persistedId());

        return new ArticleViewData(
            id: $article->persistedId(),
            title: $article->getTitle(),
            content: $this->content->renderCached(
                $article->persistedId(),
                $article->getVersion(),
                $article->getContent(),
            ),
            cover: $this->cover($article->getCover()),
            excerpt: $article->excerpt(),
            sections: $sections,
            tags: $tags,
            status: $article->status(),
            rejectionReason: $isAuthor || $canModerate ? $article->getRejectionReason() : null,
            publishedAt: $article->getPublishedAt(),
            updatedAt: $article->getUpdatedAt(),
            viewCount: $article->getViewCount(),
            author: $author === null
                ? null
                : new UserCardData(id: $author->persistedId(), name: $author->publicName()),
            permissions: $this->permissionsFor($article, $viewer, $isAuthor, $canModerate),
            reactions: $this->reactions->articleSummary($article, $viewer),
            head: $this->seo->article($article, $author, $tags, $sections),
            share: $article->isPublished() ? $this->share->links($path, $article->getTitle()) : collect(),
            poll: $this->polls->forArticle($article, $viewer),
            charts: $this->charts->presentMany(ArticleContent::chartUuids($article->getContent())),
        );
    }

    /**
     * Статья по идентификатору или null.
     */
    public function find(int $id): ?Article
    {
        return $this->articles->findEntity($id);
    }

    /**
     * Статья для формы правки автором или модератором.
     */
    public function editForm(Article $article): ArticleEditData
    {
        return new ArticleEditData(
            id: $article->persistedId(),
            title: $article->getTitle(),
            content: $this->content->renderForEditor($article->getContent()),
            summary: $article->getSummary(),
            version: $article->getVersion(),
            rejectionReason: $article->getRejectionReason(),
            seo: $article->getSeo(),
            sectionIds: $article->getSections()
                ->map(static fn (ArticleSection $section): int => $section->persistedId())
                ->values(),
            cover: $this->cover($article->getCover()),
            tags: self::tagNames($article),
            poll: $this->polls->settingsOf($article),
        );
    }

    /**
     * Какие кнопки показать: правка и удаление — автору без блокировки, реакции — другим на опубликованной статье.
     */
    private function permissionsFor(
        Article $article,
        ?User $viewer,
        bool $isAuthor,
        bool $canModerate,
    ): ArticlePermissionsData {
        $active = $viewer !== null && ! $viewer->isBanned();
        $canWrite = $isAuthor && $active;

        return new ArticlePermissionsData(
            edit: $canWrite,
            delete: $canWrite,
            moderate: $canModerate,
            react: $active && ! $isAuthor && $article->isPublished(),
        );
    }

    /**
     * Обложка в размере страницы статьи.
     */
    private function cover(?Image $cover): ?ImageData
    {
        return $this->images->presentOrNull($cover, ImagePurpose::PAGE_WIDTH);
    }

    /**
     * Названия разделов статьи по алфавиту.
     *
     * @return Collection<int, string>
     */
    private static function sectionNames(Article $article): Collection
    {
        return $article->getSections()
            ->map(static fn (ArticleSection $section): string => $section->getName())
            ->sort()
            ->values();
    }

    /**
     * Теги статьи по алфавиту.
     *
     * @return Collection<int, string>
     */
    private static function tagNames(Article $article): Collection
    {
        return $article->getTags()
            ->map(static fn (Tag $tag): string => $tag->getName())
            ->sort()
            ->values();
    }
}
