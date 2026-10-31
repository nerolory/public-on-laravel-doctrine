<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Complaint\ComplaintReason;
use Doctrine\ORM\Mapping as ORM;

/**
 * Жалоба на комментарий.
 */
#[ORM\Entity]
class CommentComplaint extends Complaint
{
    #[ORM\ManyToOne(targetEntity: Comment::class)]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private Comment $comment;

    /**
     * @param Comment         $comment  комментарий, на который жалуются
     * @param User            $reporter автор жалобы
     * @param ComplaintReason $reason   причина
     * @param string|null     $text     пояснение
     */
    public function __construct(Comment $comment, User $reporter, ComplaintReason $reason, ?string $text)
    {
        parent::__construct($reporter, $reason, $text);
        $this->comment = $comment;
    }
}
