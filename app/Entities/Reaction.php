<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Reaction\ReactionType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Реакция пользователя. Наследование одной таблицей (STI): в reactions лежат реакции на статьи и на комментарии,
 * тип строки хранится в колонке target, а у каждого подкласса своя связь. Один тип реакции — один раз на объект.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reactions')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'target', type: 'string', length: self::TARGET_LENGTH)]
#[ORM\DiscriminatorMap(['article' => ArticleReaction::class, 'comment' => CommentReaction::class])]
#[ORM\UniqueConstraint(name: 'reactions_article_unique', columns: ['user_id', 'article_id', 'type'])]
#[ORM\UniqueConstraint(name: 'reactions_comment_unique', columns: ['user_id', 'comment_id', 'type'])]
abstract class Reaction
{
    /** Длина типа цели реакции — размер колонки дискриминатора target. */
    public const int TARGET_LENGTH = 16;

    /** Длина вида реакции — размер колонки type. */
    public const int TYPE_LENGTH = 16;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: self::TYPE_LENGTH, enumType: ReactionType::class)]
    private ReactionType $type;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    /**
     * Тип и автор задаются при создании и дальше не меняются.
     */
    protected function __construct(User $user, ReactionType $type)
    {
        $this->user = $user;
        $this->type = $type;
    }
}
