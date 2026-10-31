<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use App\Enums\Media\ImagePurpose;
use App\Traits\RequiresActiveUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Загрузка картинки из редактора: текст статьи, обложка или фото альбома.
 */
final class StoreImageRequest extends FormRequest
{
    use RequiresActiveUser;

    /** Наибольший размер файла в килобайтах: фотография с современного телефона в полном качестве. */
    public const int MAX_KILOBYTES = 10240;

    /** Наибольшая сторона в пикселях: больше картинка не нужна, память на обработку растёт квадратично. */
    public const int MAX_SIDE = 8000;

    /** Форматы без анимации: их принимает и аватар. */
    public const array STATIC_MIMES = ['jpeg', 'jpg', 'png', 'webp'];

    /** Все принимаемые форматы: статичные и анимированный gif. */
    public const array MIMES = [...self::STATIC_MIMES, 'gif'];

    /**
     * Правила проверки файла и назначения.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:' . implode(',', self::MIMES),
                'max:' . self::MAX_KILOBYTES,
                'dimensions:max_width=' . self::MAX_SIDE . ',max_height=' . self::MAX_SIDE,
            ],
            'purpose' => ['required', Rule::enum(ImagePurpose::class)->only(ImagePurpose::uploadable()->all())],
        ];
    }

    /**
     * Назначение картинки.
     */
    public function purpose(): ImagePurpose
    {
        return ImagePurpose::from($this->validated('purpose'));
    }
}
