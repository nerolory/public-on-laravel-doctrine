<?php

declare(strict_types=1);

namespace App\DTO\User;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Контакты для связи из анкеты профиля.
 *
 * @implements Arrayable<string, string>
 */
final readonly class ContactsData implements Arrayable
{
    /**
     * @param string|null $phone телефон
     * @param string|null $email почта для связи
     */
    public function __construct(
        public ?string $phone = null,
        public ?string $email = null,
    ) {
    }

    /**
     * Только заполненные контакты: так они хранятся в колонке contacts и уходят в ответ API.
     *
     * @return array{phone?: string, email?: string}
     */
    public function toArray(): array
    {
        return array_filter(
            ['phone' => $this->phone, 'email' => $this->email],
            static fn (?string $value): bool => $value !== null && $value !== '',
        );
    }
}
