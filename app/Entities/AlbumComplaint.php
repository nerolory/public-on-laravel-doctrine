<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Complaint\ComplaintReason;
use Doctrine\ORM\Mapping as ORM;

/**
 * Жалоба на альбом.
 */
#[ORM\Entity]
class AlbumComplaint extends Complaint
{
    #[ORM\ManyToOne(targetEntity: Album::class)]
    #[ORM\JoinColumn(name: 'album_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private Album $album;

    /**
     * @param Album           $album    альбом, на который жалуются
     * @param User            $reporter автор жалобы
     * @param ComplaintReason $reason   причина
     * @param string|null     $text     пояснение
     */
    public function __construct(Album $album, User $reporter, ComplaintReason $reason, ?string $text)
    {
        parent::__construct($reporter, $reason, $text);
        $this->album = $album;
    }
}
