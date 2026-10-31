<?php

declare(strict_types=1);

namespace App\DTO\Legal;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Согласия пользователя для страницы подписок: состояние каждого согласия и документы, которые нужно принять.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ConsentStateData implements Arrayable
{
    /**
     * @param Collection<int, ConsentStatusData> $items   состояние каждого согласия региона
     * @param Collection<int, LegalDocumentData> $pending обязательные документы без принятия текущей версии
     */
    public function __construct(
        public Collection $items,
        public Collection $pending,
    ) {
    }

    /**
     * Состояние для ответа API.
     *
     * @return array{items: list<array<string, mixed>>, pending: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items->toArray(),
            'pending' => $this->pending->toArray(),
        ];
    }
}
