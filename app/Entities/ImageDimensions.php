<?php

declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Размеры картинки — встраиваемый объект Doctrine: колонки width и height лежат в таблице владельца.
 */
#[ORM\Embeddable]
final class ImageDimensions
{
    #[ORM\Column(type: 'integer')]
    private int $width;

    #[ORM\Column(type: 'integer')]
    private int $height;

    /**
     * @param int $width  ширина исходника в пикселях
     * @param int $height высота исходника в пикселях
     */
    public function __construct(int $width, int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Image dimensions must be positive.');
        }

        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Высота для заданной ширины с сохранением пропорций.
     */
    public function heightFor(int $width): int
    {
        // Высота пропорциональна ширине; у очень широкой картинки округление не даёт нуля.
        return max(1, (int) round($this->height * $width / $this->width));
    }
}
