<?php

declare(strict_types=1);

namespace App\Services\Reaction;

use App\DTO\Comment\CommentExcerptData;
use App\DTO\Reaction\ConflictEntryData;
use App\DTO\Reaction\ConflictScoreData;
use App\DTO\Reaction\ReactionSummaryData;
use App\Entities\Article;
use App\Entities\ArticleReaction;
use App\Entities\Comment;
use App\Entities\CommentReaction;
use App\Entities\Reaction;
use App\Entities\User;
use App\Enums\Reaction\ReactionType;
use App\Repositories\ArticleRepository;
use App\Repositories\CommentRepository;
use App\Repositories\ReactionRepository;
use App\Support\Settings\PageSizes;
use Closure;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Реакции на статьи и комментарии. На своё реагировать нельзя; повторная постановка той же реакции
 * ничего не меняет, в том числе при гонке запросов: её ловит уникальный индекс.
 */
final class ReactionService
{
    /** Поле реакции, которое ссылается на статью. */
    private const string ARTICLE_TARGET = 'article';

    /** Поле реакции, которое ссылается на комментарий. */
    private const string COMMENT_TARGET = 'comment';

    /**
     * @param ReactionRepository $reactions реакции
     * @param ArticleRepository  $articles  статьи
     * @param CommentRepository  $comments  комментарии
     * @param PageSizes          $pageSizes размеры списков из настроек
     */
    public function __construct(
        private readonly ReactionRepository $reactions,
        private readonly ArticleRepository $articles,
        private readonly CommentRepository $comments,
        private readonly PageSizes $pageSizes,
    ) {
    }

    /**
     * Ставит или снимает реакцию на статью; можно ли реагировать, решает ArticlePolicy.
     */
    public function toggleArticle(Article $article, User $user, ReactionType $type, bool $on): ReactionSummaryData
    {
        $this->toggle(
            existing: $this->reactions->findForArticle($article, $user, $type),
            on: $on,
            create: static fn (): Reaction => new ArticleReaction($article, $user, $type),
        );

        return $this->reactions->articleSummary($article, $user);
    }

    /**
     * Ставит или снимает реакцию на комментарий и обновляет его счётчики; можно ли реагировать, решает CommentPolicy.
     */
    public function toggleComment(Comment $comment, User $user, ReactionType $type, bool $on): ReactionSummaryData
    {
        $commentId = $comment->persistedId();
        $delta = $this->toggle(
            existing: $this->reactions->findForComment($comment, $user, $type),
            on: $on,
            create: static fn (): Reaction => new CommentReaction($comment, $user, $type),
        );

        if ($delta !== 0) {
            $comment->countReaction($type, $delta);
            $this->comments->flush();
        }

        return $this->reactions->commentSummaries(collect([$commentId]), $user)->get($commentId);
    }

    /**
     * Реакции на статью для её страницы.
     */
    public function articleSummary(Article $article, ?User $viewer): ReactionSummaryData
    {
        return $this->reactions->articleSummary($article, $viewer);
    }

    /**
     * Реакции на комментарии одной страницы.
     *
     * @param Collection<int, Comment> $comments
     *
     * @return Collection<int, ReactionSummaryData> сводки по идентификатору комментария
     */
    public function commentSummaries(Collection $comments, ?User $viewer): Collection
    {
        $ids = $comments->map(static fn (Comment $comment): int => $comment->persistedId());

        return $this->reactions->commentSummaries($ids, $viewer);
    }

    /**
     * Самые конфликтные статьи; статьи, которых уже нет, не показываются.
     *
     * @return Collection<int, ConflictEntryData>
     */
    public function articleConflicts(): Collection
    {
        return $this->conflicts(
            ArticleReaction::class,
            self::ARTICLE_TARGET,
            fn (Collection $ids): Collection => $this->articles->titles($ids),
            static fn (ConflictScoreData $score, string $title): ConflictEntryData => new ConflictEntryData(
                score: $score,
                articleId: $score->id,
                articleTitle: $title,
            ),
        );
    }

    /**
     * Самые конфликтные комментарии с началом текста и статьёй; комментарии, которых уже нет, не показываются.
     *
     * @return Collection<int, ConflictEntryData>
     */
    public function commentConflicts(): Collection
    {
        return $this->conflicts(
            CommentReaction::class,
            self::COMMENT_TARGET,
            fn (Collection $ids): Collection => $this->comments->excerpts($ids),
            static function (ConflictScoreData $score, CommentExcerptData $excerpt): ConflictEntryData {
                return new ConflictEntryData(
                    score: $score,
                    articleId: $excerpt->articleId,
                    articleTitle: $excerpt->articleTitle,
                    excerpt: $excerpt->body,
                );
            },
        );
    }

    /**
     * Рейтинг конфликтности по виду реакции с данными записей; записи, которых уже нет, отбрасываются.
     *
     * @param Closure(Collection<int, int>): Collection<int, mixed> $details данные записей по идентификаторам
     * @param Closure(ConflictScoreData, mixed): ConflictEntryData  $entry   строка рейтинга из оценки и данных
     *
     * @return Collection<int, ConflictEntryData>
     */
    private function conflicts(string $reactionClass, string $target, Closure $details, Closure $entry): Collection
    {
        $scores = $this->reactions->conflictRating($reactionClass, $target, $this->pageSizes->rating());
        $found = $details($scores->pluck('id'));

        return $scores
            ->filter(static fn (ConflictScoreData $score): bool => $found->has($score->id))
            ->map(static fn (ConflictScoreData $score): ConflictEntryData => $entry($score, $found->get($score->id)))
            ->values();
    }

    /**
     * Ставит или снимает реакцию и возвращает изменение счётчика: 1, -1 или 0.
     *
     * @param Closure(): Reaction $create
     */
    private function toggle(?Reaction $existing, bool $on, Closure $create): int
    {
        try {
            if ($on && $existing === null) {
                $this->reactions->add($create());

                return 1;
            }

            if (! $on && $existing !== null) {
                $this->reactions->remove($existing);

                return -1;
            }
        } catch (UniqueConstraintViolationException) {
            // Такую же реакцию только что поставил параллельный запрос: результат тот же.
        }

        return 0;
    }
}
