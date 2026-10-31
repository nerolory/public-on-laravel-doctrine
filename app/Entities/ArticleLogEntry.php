<?php

declare(strict_types=1);

namespace App\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Entity\Repository\LogEntryRepository;
use Gedmo\Loggable\LogEntryInterface;

/**
 * Запись журнала версий статьи для Gedmo Loggable. Поле data хранится в json: типа array в DBAL 4 нет.
 *
 * @implements LogEntryInterface<Article>
 */
#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
#[ORM\Table(name: 'article_log_entries')]
#[ORM\Index(name: 'article_log_object_idx', columns: ['object_class', 'object_id', 'version'])]
#[ORM\Index(name: 'article_log_date_idx', columns: ['logged_at'])]
class ArticleLogEntry implements LogEntryInterface
{
    /** Длина действия в журнале — размер колонки action. */
    public const int ACTION_LENGTH = 8;

    /** Длина идентификатора объекта — размер колонки object_id. */
    public const int OBJECT_ID_LENGTH = 64;

    /** Длина имени класса объекта — размер колонки object_class. */
    public const int OBJECT_CLASS_LENGTH = 191;

    /** Длина имени пользователя в журнале — размер колонки username. */
    public const int USERNAME_LENGTH = 191;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::ACTION_LENGTH)]
    private ?string $action = null;

    #[ORM\Column(name: 'logged_at', type: 'datetime_immutable')]
    private ?DateTimeImmutable $loggedAt = null;

    #[ORM\Column(name: 'object_id', type: 'string', length: self::OBJECT_ID_LENGTH, nullable: true)]
    private ?string $objectId = null;

    #[ORM\Column(name: 'object_class', type: 'string', length: self::OBJECT_CLASS_LENGTH)]
    private ?string $objectClass = null;

    #[ORM\Column(type: 'integer')]
    private ?int $version = null;

    /** @var array<string, mixed>|null изменённые поля версии */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;

    #[ORM\Column(type: 'string', length: self::USERNAME_LENGTH, nullable: true)]
    private ?string $username = null;

    /**
     * Идентификатор записи.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Действие: create, update или remove.
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /**
     * Действие записи.
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Пользователь, который внёс изменение.
     */
    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    /**
     * Пользователь, который внёс изменение.
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Класс изменённой сущности.
     */
    public function setObjectClass(string $objectClass): void
    {
        $this->objectClass = $objectClass;
    }

    /**
     * Класс изменённой сущности.
     */
    public function getObjectClass(): ?string
    {
        return $this->objectClass;
    }

    /**
     * Отмечает время изменения.
     */
    public function setLoggedAt(): void
    {
        $this->loggedAt = new DateTimeImmutable();
    }

    /**
     * Время изменения.
     */
    public function getLoggedAt(): ?DateTimeImmutable
    {
        return $this->loggedAt;
    }

    /**
     * Идентификатор изменённой сущности.
     */
    public function setObjectId(string $objectId): void
    {
        $this->objectId = $objectId;
    }

    /**
     * Идентификатор изменённой сущности.
     */
    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    /**
     * Изменённые поля версии.
     *
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Изменённые поля версии.
     *
     * @return array<string, mixed>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * Номер версии.
     */
    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    /**
     * Номер версии.
     */
    public function getVersion(): ?int
    {
        return $this->version;
    }
}
