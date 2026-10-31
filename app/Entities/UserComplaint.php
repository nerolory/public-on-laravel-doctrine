<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Complaint\ComplaintReason;
use Doctrine\ORM\Mapping as ORM;

/**
 * Жалоба на пользователя.
 */
#[ORM\Entity]
class UserComplaint extends Complaint
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reported_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private User $reportedUser;

    /**
     * @param User            $reportedUser пользователь, на которого жалуются
     * @param User            $reporter     автор жалобы
     * @param ComplaintReason $reason       причина
     * @param string|null     $text         пояснение
     */
    public function __construct(User $reportedUser, User $reporter, ComplaintReason $reason, ?string $text)
    {
        parent::__construct($reporter, $reason, $text);
        $this->reportedUser = $reportedUser;
    }
}
