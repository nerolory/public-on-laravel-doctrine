<?php

declare(strict_types=1);

namespace App\Services\Complaint;

use App\Contracts\ComplaintTarget;
use App\Services\Complaint\Targets\AlbumTarget;
use App\Services\Complaint\Targets\ArticleTarget;
use App\Services\Complaint\Targets\CommentTarget;
use App\Services\Complaint\Targets\UserTarget;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Типы объектов, на которые можно пожаловаться, по ключу цели.
 */
final class ComplaintTargets
{
    /** @var Collection<string, ComplaintTarget> */
    private Collection $targets;

    /**
     * Порядок целей задаёт порядок групп с одинаковым числом жалоб в очереди.
     *
     * @param ArticleTarget $article статьи
     * @param CommentTarget $comment комментарии
     * @param UserTarget    $user    пользователи
     * @param AlbumTarget   $album   альбомы
     */
    public function __construct(ArticleTarget $article, CommentTarget $comment, UserTarget $user, AlbumTarget $album)
    {
        $this->targets = collect([$article, $comment, $user, $album])
            ->keyBy(static fn (ComplaintTarget $target): string => $target->key());
    }

    /**
     * Цель по ключу; ключ приходит уже проверенным в запросе.
     */
    public function get(string $key): ComplaintTarget
    {
        return $this->targets->get($key) ?? throw new InvalidArgumentException('Unknown complaint target: ' . $key);
    }

    /**
     * Все цели по ключу.
     *
     * @return Collection<string, ComplaintTarget>
     */
    public function all(): Collection
    {
        return $this->targets;
    }

    /**
     * Ключи целей для проверки запросов.
     *
     * @return Collection<int, string>
     */
    public function keys(): Collection
    {
        return $this->targets->keys();
    }
}
