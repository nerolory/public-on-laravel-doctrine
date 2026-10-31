<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\DTO\Legal\DataExportData;
use App\DTO\Media\ImageData;
use App\Entities\Image;
use App\Entities\User;
use App\Repositories\ArticleListRepository;
use App\Repositories\ConsentRepository;
use App\Repositories\ImageRepository;
use App\Services\Media\ImageService;
use DateTimeImmutable;

/**
 * Собирает все данные пользователя в машиночитаемом виде (GDPR, ст. 20; CCPA — право знать).
 */
final class DataExportService
{
    /**
     * @param ArticleListRepository $articles     статьи
     * @param ConsentRepository     $consents     журнал согласий
     * @param ImageRepository       $images       картинки
     * @param ImageService          $imageService адреса картинок
     */
    public function __construct(
        private readonly ArticleListRepository $articles,
        private readonly ConsentRepository $consents,
        private readonly ImageRepository $images,
        private readonly ImageService $imageService,
    ) {
    }

    /**
     * Выгрузка данных пользователя; предусмотрена ли она в регионе, проверяет SitePolicy на маршруте.
     */
    public function export(User $user): DataExportData
    {
        return new DataExportData(
            exportedAt: new DateTimeImmutable(),
            id: (int) $user->getAuthIdentifier(),
            email: $user->getEmail(),
            name: $user->getName(),
            createdAt: $user->getCreatedAt(),
            emailVerifiedAt: $user->getEmailVerifiedAt(),
            profile: $user->profile(),
            articles: $this->articles->authoredBy($user),
            images: $this->images->ownedBy($user)
                ->map(fn (Image $image): ImageData => $this->imageService->present($image)),
            consents: $this->consents->history($user),
        );
    }
}
