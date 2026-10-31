<?php

declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Фото в альбоме: картинка с назначением album, описание для незрячих и место в порядке показа.
 */
#[ORM\Entity]
#[ORM\Table(name: 'album_items')]
class AlbumItem
{
    /** Длина описания фото — размер колонки alt: описание для экранных дикторов в одно-два предложения. */
    public const int ALT_LENGTH = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Album::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'album_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Album $album;

    #[ORM\ManyToOne(targetEntity: Image::class)]
    #[ORM\JoinColumn(name: 'image_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Image $image;

    #[ORM\Column(type: 'string', length: self::ALT_LENGTH, nullable: true)]
    private ?string $alt;

    #[ORM\Column(type: 'smallint')]
    private int $position;

    private function __construct()
    {
    }

    /**
     * Фото на заданном месте альбома.
     *
     * @param Album       $album    альбом
     * @param Image       $image    картинка
     * @param string|null $alt      описание для незрячих; null, если описания нет
     * @param int         $position место в порядке показа, с нуля
     */
    public static function create(Album $album, Image $image, ?string $alt, int $position): self
    {
        $item = new self();
        $item->album = $album;
        $item->image = $image;
        $item->alt = $alt;
        $item->position = $position;

        return $item;
    }

    /**
     * Картинка фото.
     */
    public function getImage(): Image
    {
        return $this->image;
    }

    /**
     * Описание фото; null, если описания нет.
     */
    public function getAlt(): ?string
    {
        return $this->alt;
    }
}
