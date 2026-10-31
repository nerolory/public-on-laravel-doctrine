<?php

declare(strict_types=1);

namespace App\DTO\Media;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Картинка для страницы: основной файл и ширины для srcset.
 *
 * @implements Arrayable<string, int|string>
 */
final readonly class ImageData implements Arrayable
{
    /**
     * @param string $id     UUID картинки
     * @param string $url    адрес основного файла
     * @param string $srcset все ширины для атрибута srcset
     * @param int    $width  ширина основного файла
     * @param int    $height высота основного файла
     */
    public function __construct(
        public string $id,
        public string $url,
        public string $srcset,
        public int $width,
        public int $height,
    ) {
    }

    /**
     * Картинка для ответа API.
     *
     * @return array{id: string, url: string, srcset: string, width: int, height: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'srcset' => $this->srcset,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
