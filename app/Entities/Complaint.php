<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Complaint\ComplaintReason;
use App\Enums\Complaint\ComplaintStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Жалоба. Наследование одной таблицей (STI): жалобы на статьи, комментарии, пользователей и альбомы
 * лежат в complaints, тип строки — колонка target. От одного пользователя — одна жалоба на объект.
 */
#[ORM\Entity]
#[ORM\Table(name: 'complaints')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'target', type: 'string', length: self::TARGET_LENGTH)]
#[ORM\DiscriminatorMap([
    'article' => ArticleComplaint::class,
    'comment' => CommentComplaint::class,
    'user' => UserComplaint::class,
    'album' => AlbumComplaint::class,
])]
#[ORM\UniqueConstraint(name: 'complaints_article_unique', columns: ['reporter_id', 'article_id'])]
#[ORM\UniqueConstraint(name: 'complaints_comment_unique', columns: ['reporter_id', 'comment_id'])]
#[ORM\UniqueConstraint(name: 'complaints_user_unique', columns: ['reporter_id', 'reported_user_id'])]
#[ORM\UniqueConstraint(name: 'complaints_album_unique', columns: ['reporter_id', 'album_id'])]
#[ORM\Index(name: 'complaints_status_idx', columns: ['status', 'id'])]
abstract class Complaint
{
    /** Длина пояснения к жалобе: модератору для решения хватает абзаца. */
    public const int TEXT_LENGTH = 1000;

    /** Длина типа цели жалобы — размер колонки дискриминатора target. */
    public const int TARGET_LENGTH = 16;

    /** Длина причины жалобы — размер колонки reason. */
    public const int REASON_LENGTH = 16;

    /** Длина статуса жалобы — размер колонки status. */
    public const int STATUS_LENGTH = 16;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reporter_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $reporter;

    #[ORM\Column(type: 'string', length: self::REASON_LENGTH, enumType: ComplaintReason::class)]
    private ComplaintReason $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text;

    #[ORM\Column(
        type: 'string',
        length: self::STATUS_LENGTH,
        enumType: ComplaintStatus::class,
        options: ['default' => 'open'],
    )]
    private ComplaintStatus $status = ComplaintStatus::Open;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'resolved_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;

    #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    /**
     * Новая открытая жалоба.
     */
    protected function __construct(User $reporter, ComplaintReason $reason, ?string $text)
    {
        $this->reporter = $reporter;
        $this->reason = $reason;
        $this->text = $text;
    }

    /**
     * Закрывает жалобу решением модератора; без модератора — автоматическим скрытием.
     */
    public function resolve(ComplaintStatus $status, ?User $moderator): void
    {
        $this->status = $status;
        $this->resolvedBy = $moderator;
        $this->resolvedAt = new DateTimeImmutable();
    }
}
