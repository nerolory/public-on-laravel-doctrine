<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Reaction\ReactionType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Реакция на комментарий.
 */
#[ORM\Entity]
class CommentReaction extends Reaction
{
    #[ORM\ManyToOne(targetEntity: Comment::class)]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private Comment $comment;

    /**
     * @param Comment      $comment комментарий, на который ставится реакция
     * @param User         $user    автор реакции
     * @param ReactionType $type    тип реакции
     */
    public function __construct(Comment $comment, User $user, ReactionType $type)
    {
        parent::__construct($user, $type);
        $this->comment = $comment;
    }
}
