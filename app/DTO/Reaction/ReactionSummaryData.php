<?php

declare(strict_types=1);

namespace App\DTO\Reaction;

use App\Enums\Reaction\ReactionType;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Реакции на статью или комментарий.
 * Счётчики по типам и реакции текущего посетителя.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ReactionSummaryData implements Arrayable
{
    /**
     * @param Collection<string, int> $counts число реакций по значению ReactionType
     * @param Collection<int, string> $mine   реакции текущего посетителя
     */
    public function __construct(
        public Collection $counts,
        public Collection $mine,
    ) {
    }

    /**
     * Сводка без реакций: все счётчики равны нулю.
     */
    public static function empty(): self
    {
        $types = collect(ReactionType::cases())->map(static fn (ReactionType $type): string => $type->value);

        return new self($types->mapWithKeys(static fn (string $type): array => [$type => 0]), collect());
    }

    /**
     * Сводка для ответа API.
     *
     * @return array{counts: array<string, int>, mine: list<string>}
     */
    public function toArray(): array
    {
        return ['counts' => $this->counts->all(), 'mine' => $this->mine->values()->all()];
    }
}
