<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\User;
use App\Enums\Media\ImagePurpose;
use App\Repositories\ImageRepository;
use Closure;
use Illuminate\Support\Collection;

/**
 * Проверка картинки по UUID из формы: назначение совпадает, картинку загрузил один из владельцев.
 */
trait ValidatesUploadedImage
{
    /**
     * Правило для UUID картинки нужного назначения, загруженной одним из владельцев.
     *
     * @param Collection<int, User> $owners     пользователи, которым разрешено использовать картинку
     * @param string                $messageKey ключ перевода сообщения об ошибке
     */
    protected function uploadedImageRule(
        ImageRepository $images,
        ImagePurpose $purpose,
        Collection $owners,
        string $messageKey,
    ): Closure {
        return static function (string $attribute, mixed $value, Closure $fail) use (
            $images,
            $purpose,
            $owners,
            $messageKey,
        ): void {
            $image = is_string($value) ? $images->findByUuid($value) : null;
            if (
                $image === null
                || $image->getPurpose() !== $purpose
                || ! $owners->contains(static fn (User $owner): bool => $image->isOwnedBy($owner))
            ) {
                $fail(__($messageKey));
            }
        };
    }
}
