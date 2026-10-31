<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Http\Requests\Media\StoreImageRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Новый аватар: картинка не меньше 64 пикселей по каждой стороне.
 */
final class UpdateAvatarRequest extends FormRequest
{
    /** Наименьшая сторона аватара: меньше уже не различить лицо. */
    private const int MIN_SIDE = 64;

    /**
     * Правила проверки файла: формат, размер и стороны.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dimensions = 'dimensions:min_width=' . self::MIN_SIDE . ',min_height=' . self::MIN_SIDE
            . ',max_width=' . StoreImageRequest::MAX_SIDE . ',max_height=' . StoreImageRequest::MAX_SIDE;

        return [
            'file' => [
                'required',
                'file',
                'mimes:' . implode(',', StoreImageRequest::STATIC_MIMES),
                'max:' . StoreImageRequest::MAX_KILOBYTES,
                $dimensions,
            ],
        ];
    }

    /**
     * Загруженный файл.
     */
    public function image(): UploadedFile
    {
        return $this->file('file');
    }
}
