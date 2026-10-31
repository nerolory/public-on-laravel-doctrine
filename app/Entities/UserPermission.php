<?php

declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Роль, выданная пользователю. Пара «пользователь + роль» уникальна.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_permissions')]
#[ORM\UniqueConstraint(name: 'user_permission_unique', columns: ['user_id', 'role_id'])]
class UserPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', nullable: false)]
    private Role $role;

    /**
     * Выдача роли пользователю.
     */
    public static function create(User $user, Role $role): self
    {
        $permission = new self();
        $permission->user = $user;
        $permission->role = $role;

        return $permission;
    }
}
