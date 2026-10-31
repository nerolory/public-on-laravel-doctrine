<?php

declare(strict_types=1);

namespace App\Services\Complaint\Targets;

use App\Contracts\ComplaintTarget;
use App\DTO\Comment\CommentExcerptData;
use App\DTO\Complaint\ComplaintLabelData;
use App\Entities\Comment;
use App\Entities\CommentComplaint;
use App\Entities\Complaint;
use App\Entities\User;
use App\Enums\Complaint\ComplaintReason;
use App\Repositories\CommentRepository;
use App\Support\Seo\SiteUrls;
use App\Traits\ChecksOwnership;
use App\Traits\ModeratorHides;
use App\Traits\ReadsNumericId;
use Illuminate\Support\Collection;

/**
 * Жалобы на комментарии: по решению модератора комментарий скрывается.
 */
final class CommentTarget implements ComplaintTarget
{
    use ChecksOwnership;
    use ModeratorHides;
    use ReadsNumericId;

    /**
     * @param CommentRepository $comments комментарии
     */
    public function __construct(
        private readonly CommentRepository $comments,
    ) {
    }

    /**
     * Ключ цели: comment.
     */
    public function key(): string
    {
        return 'comment';
    }

    /**
     * Класс комментария.
     */
    public function entityClass(): string
    {
        return Comment::class;
    }

    /**
     * Класс жалобы на комментарий.
     */
    public function complaintClass(): string
    {
        return CommentComplaint::class;
    }

    /**
     * Поле комментария в жалобе.
     */
    public function field(): string
    {
        return 'comment';
    }

    /**
     * Видимый комментарий опубликованной статьи.
     */
    public function findPublic(string $id): ?object
    {
        $numeric = $this->numericId($id);
        $comment = $numeric === null ? null : $this->comments->find($numeric);

        return $comment?->isVisible() && $comment->getArticle()->isPublished() ? $comment : null;
    }

    /**
     * Комментарий в любом состоянии.
     */
    public function find(int $id): ?object
    {
        return $this->comments->find($id);
    }

    /**
     * @param Comment $entity
     */
    public function complain(object $entity, User $reporter, ComplaintReason $reason, ?string $text): Complaint
    {
        return new CommentComplaint($entity, $reporter, $reason, $text);
    }

    /**
     * @param Comment $entity
     */
    public function hide(object $entity): void
    {
        $entity->hide();
    }

    /**
     * Начало текста комментария со ссылкой на статью.
     */
    public function labels(Collection $ids): Collection
    {
        $excerpts = $this->comments->excerpts($ids);

        return $ids->mapWithKeys(static function (int $id) use ($excerpts): array {
            /** @var CommentExcerptData|null $excerpt */
            $excerpt = $excerpts->get($id);

            return [$id => $excerpt === null
                ? new ComplaintLabelData(ComplaintLabelData::MISSING_TEXT)
                : new ComplaintLabelData($excerpt->body, SiteUrls::articlePath($excerpt->articleId))];
        });
    }
}
