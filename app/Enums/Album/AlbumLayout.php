<?php

declare(strict_types=1);

namespace App\Enums\Album;

/**
 * Вид показа альбома в статье: значение хранится в атрибуте data-layout HTML статьи.
 */
enum AlbumLayout: string
{
    /** Горизонтальная лента с прокруткой (scroll-snap). */
    case Slider = 'slider';

    /** Сетка фотографий. */
    case Collage = 'collage';

    /** Брейкпоинт узкого экрана: совпадает с $width-reading (800px) во фронтенде, где колонка чтения сжимается. */
    private const string NARROW_SCREEN = '(max-width: 800px)';

    /** Доля ширины экрана, которую занимает кадр ленты на узком экране: соответствует flex 90% во фронтенде. */
    private const string SLIDER_NARROW = '90vw';

    /** Ширина кадра ленты на широком экране: совпадает с $width-album-slider (720px) во фронтенде. */
    private const string SLIDER_WIDE = '720px';

    /** Ширина крупного фото коллажа на узком экране: первое фото занимает две колонки сетки, половину экрана. */
    private const string COLLAGE_NARROW = '50vw';

    /** Ширина крупного фото коллажа на широком экране: две колонки из четырёх в колонке чтения $width-reading. */
    private const string COLLAGE_WIDE = '400px';

    /**
     * Атрибут sizes фото: ширина колонки на узком и широком экране.
     */
    public function sizes(): string
    {
        return match ($this) {
            self::Slider => self::NARROW_SCREEN . ' ' . self::SLIDER_NARROW . ', ' . self::SLIDER_WIDE,
            self::Collage => self::NARROW_SCREEN . ' ' . self::COLLAGE_NARROW . ', ' . self::COLLAGE_WIDE,
        };
    }
}
