<?php

declare(strict_types=1);

namespace App\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Значение настройки сайта. Ключ — стабильный бизнес-идентификатор, например `mail.host`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'settings')]
class Setting
{
    /** Длина ключа настройки — размер колонки key: группа и имя поля через точку. */
    public const int KEY_LENGTH = 120;

    #[ORM\Id]
    #[ORM\Column(name: 'key', type: 'string', length: self::KEY_LENGTH)]
    private string $key;

    #[ORM\Column(type: 'json', nullable: true)]
    private mixed $value = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * Новая настройка с первым значением.
     */
    public static function create(string $key, mixed $value): self
    {
        $setting = new self();
        $setting->key = $key;
        $setting->change($value);

        return $setting;
    }

    /**
     * Меняет значение и время изменения.
     */
    public function change(mixed $value): void
    {
        $this->value = $value;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Ключ настройки.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Сохранённое значение.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
