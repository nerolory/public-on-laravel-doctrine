<?php

declare(strict_types=1);

namespace App\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Сырой просмотр статьи для статистики; срок хранения задаётся в настройках. Посетитель — хеш IP и браузера с секретом
 * приложения: самих адресов в базе нет. Уникальность по статье, посетителю и дню — один просмотр в сутки.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'article_views')]
#[ORM\UniqueConstraint(name: 'article_views_daily_unique', columns: ['article_id', 'visitor_hash', 'viewed_on'])]
#[ORM\Index(name: 'article_views_created_idx', columns: ['created_at'])]
class ArticleView
{
    /** Длина домена источника перехода — размер колонки referrer_host: доменное имя не длиннее 253 символов. */
    public const int REFERRER_LENGTH = 255;

    /** Длина хеша посетителя — размер колонки visitor_hash: hex-вывод HMAC-SHA256. */
    public const int VISITOR_HASH_LENGTH = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'visitor_hash', type: 'string', length: self::VISITOR_HASH_LENGTH)]
    private string $visitorHash;

    #[ORM\Column(name: 'viewed_on', type: 'date_immutable')]
    private DateTimeImmutable $viewedOn;

    /** Только домен источника перехода, без пути и параметров. */
    #[ORM\Column(name: 'referrer_host', type: 'string', length: self::REFERRER_LENGTH, nullable: true)]
    private ?string $referrerHost = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * Строки вставляет репозиторий напрямую через DBAL.
     */
    private function __construct()
    {
    }
}
