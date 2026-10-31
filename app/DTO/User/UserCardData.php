<?php

declare(strict_types=1);

namespace App\DTO\User;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Пользователь в публичных списках: идентификатор, публичное имя и аватар.
 *
 * @implements Arrayable<string, int|string|null>
 */
final readonly class UserCardData implements Arrayable
{
    /**
     * @param int         $id     идентификатор пользователя
     * @param string      $name   публичное имя
     * @param string|null $avatar адрес маленького аватара
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $avatar = null,
    ) {
    }

    /**
     * Пользователь для ответа API.
     *
     * @return array{id: int, name: string, avatar: string|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'avatar' => $this->avatar];
    }

    /**
     * Ссылка на пользователя без аватара: автор статьи, владелец альбома, адресат ответа.
     *
     * @return array{id: int, name: string}
     */
    public function reference(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
