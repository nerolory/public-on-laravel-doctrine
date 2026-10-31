<?php

declare(strict_types=1);

namespace App\Services\Complaint\Targets;

use App\Contracts\ComplaintTarget;
use App\DTO\Complaint\ComplaintLabelData;
use App\Entities\Article;
use App\Entities\ArticleComplaint;
use App\Entities\Complaint;
use App\Entities\User;
use App\Enums\Complaint\ComplaintReason;
use App\Repositories\ArticleRepository;
use App\Support\Seo\SiteUrls;
use App\Traits\ChecksOwnership;
use App\Traits\HidesByRejection;
use App\Traits\ModeratorHides;
use App\Traits\ReadsNumericId;
use Illuminate\Support\Collection;

/**
 * Жалобы на статьи: по решению модератора статья отклоняется с пометкой о жалобах.
 */
final class ArticleTarget implements ComplaintTarget
{
    use ChecksOwnership;
    use HidesByRejection;
    use ModeratorHides;
    use ReadsNumericId;

    /**
     * @param ArticleRepository $articles статьи
     */
    public function __construct(
        private readonly ArticleRepository $articles,
    ) {
    }

    /**
     * Ключ цели: article.
     */
    public function key(): string
    {
        return 'article';
    }

    /**
     * Класс статьи.
     */
    public function entityClass(): string
    {
        return Article::class;
    }

    /**
     * Класс жалобы на статью.
     */
    public function complaintClass(): string
    {
        return ArticleComplaint::class;
    }

    /**
     * Поле статьи в жалобе.
     */
    public function field(): string
    {
        return 'article';
    }

    /**
     * Опубликованная статья.
     */
    public function findPublic(string $id): ?object
    {
        $numeric = $this->numericId($id);
        $article = $numeric === null ? null : $this->articles->findEntity($numeric);

        return $article?->isPublished() ? $article : null;
    }

    /**
     * Статья в любом состоянии.
     */
    public function find(int $id): ?object
    {
        return $this->articles->findEntity($id);
    }

    /**
     * @param Article $entity
     */
    public function complain(object $entity, User $reporter, ComplaintReason $reason, ?string $text): Complaint
    {
        return new ArticleComplaint($entity, $reporter, $reason, $text);
    }

    /**
     * Заголовки статей со ссылками.
     */
    public function labels(Collection $ids): Collection
    {
        $titles = $this->articles->titles($ids);

        return $ids->mapWithKeys(static fn (int $id): array => [$id => new ComplaintLabelData(
            text: $titles->get($id, ComplaintLabelData::MISSING_TEXT),
            url: SiteUrls::articlePath($id),
        )]);
    }
}
