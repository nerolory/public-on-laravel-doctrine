<?php

declare(strict_types=1);

namespace App\Services\Article;

use App\Entities\Article;
use App\Enums\Moderation\ModerationAction;
use App\Repositories\ArticleRepository;
use App\Traits\RequiresAllFound;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Support\Collection;

/**
 * Смена состояния статей: модерация списком, публикация, удаление модератором и архивирование автором.
 */
final class ArticleModerationService
{
    use RequiresAllFound;

    /**
     * @param ArticleRepository $articles статьи
     */
    public function __construct(
        private readonly ArticleRepository $articles,
    ) {
    }

    /**
     * Действие модератора над выбранными статьями.
     *
     * @param Collection<int, int> $ids
     *
     * @throws EntityNotFoundException одной из статей нет
     */
    public function moderate(Collection $ids, ModerationAction $action, ?string $reason = null): void
    {
        $articles = $this->articles->findByIds($ids);
        $this->requireAllFound(
            $ids,
            $articles->map(static fn (Article $article): int => $article->persistedId()),
            Article::class,
            'id',
        );

        $action->perform($articles, $this->articles, $reason);
    }

    /**
     * Публикует статью.
     */
    public function publish(Article $article): void
    {
        ModerationAction::Approve->perform(collect([$article]), $this->articles, reason: null);
    }

    /**
     * Удаляет статью модератором.
     */
    public function delete(Article $article): void
    {
        ModerationAction::Delete->perform(collect([$article]), $this->articles, reason: null);
    }

    /**
     * Удаление автором: статья уходит в архив с причиной; с сайта она пропадает, запись в базе сохраняется.
     */
    public function archive(Article $article, string $reason): void
    {
        $this->articles->archive($article, $reason);
    }
}
