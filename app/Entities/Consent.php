<?php

declare(strict_types=1);

namespace App\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Запись журнала согласий: кто, на какую версию текста и когда дал согласие или отозвал его.
 * Журнал только дополняется (readOnly-сущность); текущее состояние — последняя запись по виду согласия.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'consents')]
#[ORM\Index(name: 'consents_user_idx', columns: ['user_id', 'created_at'])]
class Consent
{
    /** Длина сохраняемой строки браузера: размер колонки user_agent. */
    public const int USER_AGENT_LENGTH = 255;

    /** Длина IP-адреса в текстовом виде — размер колонки ip_address: наибольший IPv6 с суффиксом IPv4. */
    public const int IP_ADDRESS_LENGTH = 45;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: LegalDocument::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false)]
    private LegalDocument $document;

    #[ORM\Column(name: 'is_agree', type: 'boolean')]
    private bool $agreed;

    #[ORM\Column(name: 'ip_address', type: 'string', length: self::IP_ADDRESS_LENGTH, nullable: true)]
    private ?string $ipAddress;

    #[ORM\Column(name: 'user_agent', type: 'string', length: self::USER_AGENT_LENGTH, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * Новая запись журнала: согласие ($agreed) или отзыв на версию документа.
     */
    public static function record(
        User $user,
        LegalDocument $document,
        bool $agreed,
        ?string $ipAddress,
        ?string $userAgent,
    ): self {
        $consent = new self();
        $consent->user = $user;
        $consent->document = $document;
        $consent->agreed = $agreed;
        $consent->ipAddress = $ipAddress;
        $consent->userAgent = $userAgent === null ? null : mb_substr($userAgent, 0, self::USER_AGENT_LENGTH);
        $consent->createdAt = new DateTimeImmutable();

        return $consent;
    }

    /**
     * Версия документа, к которой относится запись.
     */
    public function getDocument(): LegalDocument
    {
        return $this->document;
    }

    /**
     * Согласие дано (true) или отозвано.
     */
    public function isAgreed(): bool
    {
        return $this->agreed;
    }
}
