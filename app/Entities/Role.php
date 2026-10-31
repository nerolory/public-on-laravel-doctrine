<?php

declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Роль пользователя. Коды ролей — стабильные бизнес-ключи; подписи лежат в lang/{locale}/roles.php.
 */
#[ORM\Entity]
#[ORM\Table(name: 'roles')]
class Role
{
    /** Обычный пользователь: роль есть у всех. */
    public const string USER = 'user';

    /** Контент-менеджер: разделы и статьи. */
    public const string CONTENT_MANAGER = 'content_manager';

    /** Модератор: публикация, жалобы, блокировки. */
    public const string MODERATOR = 'moderator';

    /** Администратор: пользователи и настройки. */
    public const string ADMINISTRATOR = 'administrator';

    /** Владелец сайта: все права, включая назначение администраторов. */
    public const string OWNER = 'owner';

    /** Все коды ролей по возрастанию прав. */
    public const array CODES = [self::USER, self::CONTENT_MANAGER, self::MODERATOR, self::ADMINISTRATOR, self::OWNER];

    /** Роли, которые управляют пользователями и настройками. */
    public const array MANAGERS = [self::OWNER, self::ADMINISTRATOR];

    /** Роли, которым открыта админка. */
    public const array MODERATORS = [self::OWNER, self::ADMINISTRATOR, self::MODERATOR];

    /** Длина кода роли — размер колонки code. */
    public const int CODE_LENGTH = 40;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::CODE_LENGTH, unique: true)]
    private string $code;

    /**
     * Роль с известным кодом.
     */
    public static function create(string $code): self
    {
        if (! in_array($code, self::CODES, strict: true)) {
            throw new InvalidArgumentException('Unknown role: ' . $code);
        }

        $role = new self();
        $role->code = $code;

        return $role;
    }

    /**
     * Код роли.
     */
    public function getCode(): string
    {
        return $this->code;
    }
}
