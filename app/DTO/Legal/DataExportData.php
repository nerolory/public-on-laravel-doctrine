<?php

declare(strict_types=1);

namespace App\DTO\Legal;

use App\DTO\Article\ArticleExportData;
use App\DTO\Media\ImageData;
use App\DTO\User\ProfileData;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Все данные пользователя в машиночитаемом виде.
 * Основание: GDPR, ст. 20; CCPA, право знать.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class DataExportData implements Arrayable
{
    /**
     * @param DateTimeImmutable                  $exportedAt      время выгрузки
     * @param int                                $id              идентификатор пользователя
     * @param string                             $email           адрес почты
     * @param string                             $name            полное имя
     * @param DateTimeImmutable|null             $createdAt       дата регистрации
     * @param DateTimeImmutable|null             $emailVerifiedAt дата подтверждения email
     * @param ProfileData                        $profile         анкета профиля
     * @param Collection<int, ArticleExportData> $articles        статьи пользователя
     * @param Collection<int, ImageData>         $images          загруженные картинки
     * @param Collection<int, ConsentRecordData> $consents        журнал согласий
     */
    public function __construct(
        public DateTimeImmutable $exportedAt,
        public int $id,
        public string $email,
        public string $name,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $emailVerifiedAt,
        public ProfileData $profile,
        public Collection $articles,
        public Collection $images,
        public Collection $consents,
    ) {
    }

    /**
     * Содержимое файла выгрузки.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exported_at' => $this->exportedAt->format(DATE_ATOM),
            'account' => [
                'id' => $this->id,
                'email' => $this->email,
                'name' => $this->name,
                'created_at' => $this->createdAt?->format(DATE_ATOM),
                'email_verified_at' => $this->emailVerifiedAt?->format(DATE_ATOM),
            ],
            'profile' => [
                ...$this->profile->toArray(),
                'visibility' => $this->profile->visibility->all(),
            ],
            'articles' => $this->articles->toArray(),
            'images' => $this->images->values()->toArray(),
            'consents' => $this->consents->values()->toArray(),
        ];
    }
}
