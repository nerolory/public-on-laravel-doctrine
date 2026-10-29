<?php

declare(strict_types=1);

namespace App\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use LaravelDoctrine\ORM\Auth\Authenticatable;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\AttributeOverrides([
    new ORM\AttributeOverride(name: 'rememberToken', column: new ORM\Column(name: 'remember_token', type: 'string', length: 100, nullable: true)),
])]
class User implements AuthenticatableContract
{
    use Authenticatable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(name: 'email_verified_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;
}
