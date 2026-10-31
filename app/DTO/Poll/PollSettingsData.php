<?php

declare(strict_types=1);

namespace App\DTO\Poll;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Опрос для формы правки статьи.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PollSettingsData implements Arrayable
{
    /**
     * @param PollData $poll   параметры опроса
     * @param bool     $locked за варианты уже голосовали, менять их нельзя
     */
    public function __construct(
        public PollData $poll,
        public bool $locked,
    ) {
    }

    /**
     * Опрос для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->poll->toArray() + ['locked' => $this->locked];
    }
}
