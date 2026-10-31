<?php

declare(strict_types=1);

namespace App\Http\Requests\Album;

use App\DTO\Album\AlbumData;
use App\DTO\Album\AlbumItemData;
use App\Entities\Album;
use App\Entities\AlbumItem;
use App\Enums\Media\ImagePurpose;
use App\Repositories\ImageRepository;
use App\Support\Settings\ContentLimits;
use App\Traits\RequiresActiveUser;
use App\Traits\ValidatesUploadedImage;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Альбом из формы «Мои альбомы»: название и фото по порядку.
 */
final class StoreAlbumRequest extends FormRequest
{
    use RequiresActiveUser;
    use ValidatesUploadedImage;

    /**
     * Правила проверки альбома; число фото ограничено настройками.
     *
     * @return array<string, mixed>
     */
    public function rules(ContentLimits $limits, ImageRepository $images): array
    {
        return [
            'title' => ['required', 'string', 'max:' . Album::TITLE_LENGTH],
            'items' => ['required', 'array', 'min:1', 'max:' . $limits->albumPhotos()],
            'items.*.image' => ['required', 'uuid', 'distinct', $this->imageRule($images)],
            'items.*.alt' => ['nullable', 'string', 'max:' . AlbumItem::ALT_LENGTH],
        ];
    }

    /**
     * Проверенный альбом без лишних пробелов.
     */
    public function albumData(): AlbumData
    {
        $items = collect($this->validated('items'))->map(static fn (array $item): AlbumItemData => new AlbumItemData(
            image: $item['image'],
            alt: self::trimmedAlt(Arr::get($item, 'alt')),
        ));

        return new AlbumData(trim($this->validated('title')), $items->values());
    }

    /**
     * Фото загружено этим пользователем для альбома.
     */
    private function imageRule(ImageRepository $images): Closure
    {
        return $this->uploadedImageRule(
            $images,
            ImagePurpose::Album,
            collect([$this->user()]),
            'messages.albums.image_invalid',
        );
    }

    /**
     * Описание фото без пробелов по краям; пустое описание хранится как null.
     */
    private static function trimmedAlt(mixed $alt): ?string
    {
        $trimmed = $alt === null ? null : trim($alt);

        return $trimmed === '' ? null : $trimmed;
    }
}
