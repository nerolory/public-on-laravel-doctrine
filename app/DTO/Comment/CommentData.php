<?php

declare(strict_types=1);

namespace App\DTO\Comment;

use App\DTO\Reaction\ReactionSummaryData;
use App\DTO\User\UserCardData;
use App\Enums\Comment\CommentState;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Комментарий для страницы статьи.
 * У удалённого или скрытого текст и автор не передаются.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CommentData implements Arrayable
{
    /**
     * @param int                    $id          идентификатор комментария
     * @param int|null               $parentId    корень ветки; у корня null
     * @param string|null            $body        текст видимого комментария
     * @param CommentState           $state       состояние комментария
     * @param UserCardData|null      $author      автор видимого комментария
     * @param UserCardData|null      $replyTo     адресат ответа внутри ветки
     * @param DateTimeImmutable      $createdAt   время публикации
     * @param bool                   $edited      текст изменён после публикации
     * @param int                    $replyCount  число ответов в ветке
     * @param ReactionSummaryData    $reactions   реакции на комментарий
     * @param CommentPermissionsData $permissions доступные посетителю действия
     */
    public function __construct(
        public int $id,
        public ?int $parentId,
        public ?string $body,
        public CommentState $state,
        public ?UserCardData $author,
        public ?UserCardData $replyTo,
        public DateTimeImmutable $createdAt,
        public bool $edited,
        public int $replyCount,
        public ReactionSummaryData $reactions,
        public CommentPermissionsData $permissions,
    ) {
    }

    /**
     * Комментарий для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'body' => $this->body,
            'state' => $this->state->value,
            'author' => $this->author?->toArray(),
            'reply_to' => $this->replyTo?->reference(),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'edited' => $this->edited,
            'reply_count' => $this->replyCount,
            'reactions' => $this->reactions->toArray(),
            'permissions' => $this->permissions->toArray(),
        ];
    }
}
