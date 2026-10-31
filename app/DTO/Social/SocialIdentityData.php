<?php

declare(strict_types=1);

namespace App\DTO\Social;

/**
 * Пользователь соцсети после входа.
 * Хранится в сессии до завершения регистрации.
 */
final readonly class SocialIdentityData
{
    /**
     * @param string      $provider ключ провайдера
     * @param string      $id       идентификатор пользователя у провайдера
     * @param string|null $email    адрес почты, если провайдер его передал
     * @param string|null $name     имя у провайдера
     */
    public function __construct(
        public string $provider,
        public string $id,
        public ?string $email,
        public ?string $name,
    ) {
    }

    /**
     * Данные из сессии.
     *
     * @param array{provider: string, id: string, email: string|null, name: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: $data['provider'],
            id: $data['id'],
            email: $data['email'],
            name: $data['name'],
        );
    }

    /**
     * Данные для сессии.
     *
     * @return array{provider: string, id: string, email: string|null, name: string|null}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
        ];
    }
}
