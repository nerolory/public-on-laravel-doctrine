<?php

declare(strict_types=1);

namespace App\DTO\User;

use App\DTO\Media\ImageData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Аккаунт для его владельца: все поля профиля, включая скрытые, и доступные действия.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AccountData implements Arrayable
{
    /**
     * @param int                     $id              идентификатор пользователя
     * @param string                  $email           адрес почты
     * @param bool                    $emailVerified   подтверждён ли адрес
     * @param string                  $name            полное имя
     * @param string                  $publicName      имя, которое видят посетители
     * @param ImageData|null          $avatar          аватар
     * @param ProfileData             $profile         анкета профиля
     * @param Collection<int, string> $networks        соцсети, доступные в анкете
     * @param AccountCapabilitiesData $capabilities    доступные действия
     * @param int                     $pendingConsents обязательных документов, которые нужно принять
     */
    public function __construct(
        public int $id,
        public string $email,
        public bool $emailVerified,
        public string $name,
        public string $publicName,
        public ?ImageData $avatar,
        public ProfileData $profile,
        public Collection $networks,
        public AccountCapabilitiesData $capabilities,
        public int $pendingConsents,
    ) {
    }

    /**
     * Аккаунт для ответа API; контакты и ссылки уходят объектами, даже пустые.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'name' => $this->name,
            'public_name' => $this->publicName,
            'avatar' => $this->avatar?->toArray(),
            'profile' => $this->profile->toArray(),
            'visibility' => $this->profile->visibility->all(),
            'networks' => $this->networks->values()->all(),
            'capabilities' => $this->capabilities->toArray(),
            'pending_consents' => $this->pendingConsents,
        ];
    }
}
