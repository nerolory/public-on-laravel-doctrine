<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\SocialAccount;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Привязки учётных записей соцсетей.
 */
final class SocialAccountRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Привязка по провайдеру и идентификатору пользователя у провайдера.
     */
    public function find(string $provider, string $providerUserId): ?SocialAccount
    {
        return $this->entityManager->getRepository(SocialAccount::class)
            ->findOneBy(['provider' => $provider, 'providerUserId' => $providerUserId]);
    }

    /**
     * Сохраняет привязку.
     */
    public function save(SocialAccount $account): void
    {
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }
}
