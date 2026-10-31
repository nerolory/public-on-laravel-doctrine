<?php

declare(strict_types=1);

namespace App\DTO\Poll;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Опрос на странице статьи: параметры, итоги и выбор посетителя.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PollViewData implements Arrayable
{
    /**
     * @param string                              $id          UUID опроса
     * @param string                              $question    вопрос
     * @param bool                                $multiple    можно несколько
     * @param int                                 $maxChoices  сколько выбрать
     * @param DateTimeImmutable|null              $endsAt      окончание голосования
     * @param bool                                $closed      голосование завершено
     * @param bool                                $allowRevote можно изменить голос
     * @param bool                                $anonymous   имена скрыты
     * @param int                                 $totalVoters число проголосовавших
     * @param Collection<int, PollOptionViewData> $options     варианты с итогами
     * @param Collection<int, int>|null           $myChoice    выбор; null — не голосовал
     * @param bool                                $canVote     посетитель может голосовать
     */
    public function __construct(
        public string $id,
        public string $question,
        public bool $multiple,
        public int $maxChoices,
        public ?DateTimeImmutable $endsAt,
        public bool $closed,
        public bool $allowRevote,
        public bool $anonymous,
        public int $totalVoters,
        public Collection $options,
        public ?Collection $myChoice,
        public bool $canVote,
    ) {
    }

    /**
     * Опрос для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'multiple' => $this->multiple,
            'max_choices' => $this->maxChoices,
            'ends_at' => $this->endsAt?->format(DATE_ATOM),
            'closed' => $this->closed,
            'allow_revote' => $this->allowRevote,
            'anonymous' => $this->anonymous,
            'total_voters' => $this->totalVoters,
            'options' => $this->options->toArray(),
            'my_choice' => $this->myChoice?->values()->all(),
            'can_vote' => $this->canVote,
        ];
    }
}
