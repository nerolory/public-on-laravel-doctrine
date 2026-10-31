<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Reaction\ReactionType;
use App\Traits\HasOwner;
use App\Traits\HasPersistedId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Комментарий к статье в стиле YouTube: ветка — корневой комментарий, ответы лежат одним уровнем под ним,
 * а адресат ответа хранится отдельно (replyTo). Удалённый или скрытый комментарий с ответами остаётся
 * заглушкой, чтобы ветка не распалась. Счётчики ответов и реакций денормализованы для сортировки «популярные».
 */
#[ORM\Entity]
#[ORM\Table(name: 'comments')]
#[ORM\Index(name: 'comments_thread_idx', columns: ['article_id', 'parent_id', 'id'])]
#[ORM\Index(name: 'comments_top_idx', columns: ['article_id', 'parent_id', 'score'])]
class Comment
{
    use HasOwner;
    use HasPersistedId;

    /** Длина начала текста в списках админки: как у анонса статьи, строка-две в таблице. */
    public const int EXCERPT_LENGTH = Article::SUMMARY_LENGTH;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    /** Корень ветки; у самого корня — null. */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Comment $parent = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reply_to_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $replyTo = null;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(name: 'reply_count', type: 'integer', options: ['default' => 0])]
    private int $replyCount = 0;

    #[ORM\Column(name: 'positive_count', type: 'integer', options: ['default' => 0])]
    private int $positiveCount = 0;

    #[ORM\Column(name: 'negative_count', type: 'integer', options: ['default' => 0])]
    private int $negativeCount = 0;

    /** Положительные минус отрицательные — порядок «сначала популярные». */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $score = 0;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'edited_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $editedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    /** Скрыт модератором или автоматически по жалобам. */
    #[ORM\Column(name: 'hidden_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $hiddenAt = null;

    private function __construct()
    {
    }

    /**
     * Новый комментарий. Ответ на ответ попадает в ту же ветку, а его автор становится адресатом.
     */
    public static function post(Article $article, User $author, string $body, ?Comment $repliedTo = null): self
    {
        $comment = new self();
        $comment->article = $article;
        $comment->author = $author;
        $comment->body = $body;
        if ($repliedTo !== null) {
            $comment->parent = $repliedTo->parent ?? $repliedTo;
            $comment->replyTo = $repliedTo->parent === null ? null : $repliedTo->author;
            $comment->parent->replyCount++;
        }

        return $comment;
    }

    /**
     * Правка текста автором.
     */
    public function edit(string $body): void
    {
        $this->body = $body;
        $this->editedAt = new DateTimeImmutable();
    }

    /**
     * Удаление: с ответами комментарий остаётся заглушкой.
     */
    public function delete(): void
    {
        $this->deletedAt ??= new DateTimeImmutable();
    }

    /**
     * Скрытие модератором или по жалобам.
     */
    public function hide(): void
    {
        $this->hiddenAt ??= new DateTimeImmutable();
    }

    /**
     * Учитывает поставленную (+1) или снятую (-1) реакцию в счётчиках и оценке.
     */
    public function countReaction(ReactionType $type, int $delta): void
    {
        if ($type->isPositive()) {
            $this->positiveCount += $delta;
            $this->recountScore();

            return;
        }

        $this->negativeCount += $delta;
        $this->recountScore();
    }

    /**
     * Виден ли текст комментария на сайте.
     */
    public function isVisible(): bool
    {
        return $this->deletedAt === null && $this->hiddenAt === null;
    }

    /**
     * Идентификатор; до сохранения null.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Статья комментария.
     */
    public function getArticle(): Article
    {
        return $this->article;
    }

    /**
     * Автор комментария.
     */
    public function getAuthor(): User
    {
        return $this->author;
    }

    /**
     * Корень ветки; у корня null.
     */
    public function getParent(): ?Comment
    {
        return $this->parent;
    }

    /**
     * Адресат ответа внутри ветки.
     */
    public function getReplyTo(): ?User
    {
        return $this->replyTo;
    }

    /**
     * Текст комментария.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Число ответов в ветке.
     */
    public function getReplyCount(): int
    {
        return $this->replyCount;
    }

    /**
     * Время публикации.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Время последней правки.
     */
    public function getEditedAt(): ?DateTimeImmutable
    {
        return $this->editedAt;
    }

    /**
     * Удалён ли комментарий.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Скрыт ли комментарий.
     */
    public function isHidden(): bool
    {
        return $this->hiddenAt !== null;
    }

    /**
     * Оценка для сортировки «популярные»: положительные реакции минус отрицательные.
     */
    private function recountScore(): void
    {
        $this->score = $this->positiveCount - $this->negativeCount;
    }

    /**
     * Владелец комментария — его автор.
     */
    private function owner(): User
    {
        return $this->author;
    }
}
