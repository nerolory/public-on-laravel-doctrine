<?php

declare(strict_types=1);

namespace App\DTO\Poll;

use Illuminate\Support\Collection;

/**
 * Итоги опроса: голоса по вариантам и число проголосовавших.
 */
final readonly class PollResultsData
{
    /**
     * @param Collection<int, int> $votes  голоса по идентификатору варианта
     * @param int                  $voters число проголосовавших
     */
    public function __construct(
        public Collection $votes,
        public int $voters,
    ) {
    }

    /**
     * Голоса за вариант.
     */
    public function votesFor(int $optionId): int
    {
        return (int) $this->votes->get($optionId, 0);
    }
}
