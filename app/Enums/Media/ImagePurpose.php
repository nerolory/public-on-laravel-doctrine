<?php

declare(strict_types=1);

namespace App\Enums\Media;

use Illuminate\Support\Collection;

/**
 * Назначение картинки определяет набор размеров, к которому она приводится.
 */
enum ImagePurpose: string
{
    /** Картинка в тексте статьи. */
    case Content = 'content';

    /** Обложка статьи. */
    case Cover = 'cover';

    /** Аватар пользователя. */
    case Avatar = 'avatar';

    /** Картинка альбома. */
    case Album = 'album';

    /** Ширина картинок на странице статьи и альбома: колонка текста на широком экране; входит в набор ширин. */
    public const int PAGE_WIDTH = 960;

    /** Ширины аватара: показ до 128 пикселей, вдвое больше — для экранов с двойной плотностью. */
    private const array AVATAR_WIDTHS = [64, 128, 256];

    /** Ширины обложки: колонка статьи и полная ширина страницы. */
    private const array COVER_WIDTHS = [480, self::PAGE_WIDTH, 1440];

    /** Ширины картинок текста и альбомов: от телефона до широкого экрана. */
    private const array CONTENT_WIDTHS = [320, 640, self::PAGE_WIDTH, 1280, 1920];

    /**
     * Ширины в пикселях по возрастанию. Картинка не увеличивается: размеры больше исходника пропускаются.
     *
     * @return Collection<int, int>
     */
    public function widths(): Collection
    {
        return collect(match ($this) {
            self::Avatar => self::AVATAR_WIDTHS,
            self::Cover => self::COVER_WIDTHS,
            self::Content, self::Album => self::CONTENT_WIDTHS,
        });
    }

    /**
     * Аватар обрезается до квадрата, остальные картинки сохраняют пропорции.
     */
    public function isSquare(): bool
    {
        return $this === self::Avatar;
    }

    /**
     * Назначения, которые можно указать при загрузке через редактор; аватар загружается в профиле.
     *
     * @return Collection<int, self>
     */
    public static function uploadable(): Collection
    {
        return collect([self::Content, self::Cover, self::Album]);
    }
}
