<?php

declare(strict_types=1);

namespace App\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Привязка учётной записи соцсети к пользователю. Пара «провайдер + id в провайдере» уникальна.
 */
#[ORM\Entity]
#[ORM\Table(name: 'social_accounts')]
#[ORM\UniqueConstraint(name: 'social_accounts_provider_unique', columns: ['provider', 'provider_user_id'])]
class SocialAccount
{
    /** Длина ключа провайдера — размер колонки provider. */
    public const int PROVIDER_LENGTH = 30;

    /** Длина идентификатора пользователя у провайдера — размер колонки provider_user_id. */
    public const int PROVIDER_USER_ID_LENGTH = 191;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: self::PROVIDER_LENGTH)]
    private string $provider;

    #[ORM\Column(name: 'provider_user_id', type: 'string', length: self::PROVIDER_USER_ID_LENGTH)]
    private string $providerUserId;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * Привязывает учётную запись провайдера к пользователю.
     */
    public static function link(User $user, string $provider, string $providerUserId): self
    {
        $account = new self();
        $account->user = $user;
        $account->provider = $provider;
        $account->providerUserId = $providerUserId;
        $account->createdAt = new DateTimeImmutable();

        return $account;
    }

    /**
     * Пользователь сайта.
     */
    public function getUser(): User
    {
        return $this->user;
    }
}
