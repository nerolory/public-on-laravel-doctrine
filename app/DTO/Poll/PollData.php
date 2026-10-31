<?php

declare(strict_types=1);

namespace App\DTO\Poll;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Параметры опроса из формы статьи.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PollData implements Arrayable
{
    /**
     * @param string                  $question    вопрос
     * @param Collection<int, string> $options     варианты ответа по порядку
     * @param bool                    $multiple    можно выбрать несколько вариантов
     * @param int|null                $maxChoices  лимит выбора; null — без лимита
     * @param DateTimeImmutable|null  $endsAt      окончание голосования; null — без срока
     * @param bool                    $allowRevote можно изменить или отозвать голос
     * @param bool                    $anonymous   имена проголосовавших скрыты
     */
    public function __construct(
        public string $question,
        public Collection $options,
        public bool $multiple = false,
        public ?int $maxChoices = null,
        public ?DateTimeImmutable $endsAt = null,
        public bool $allowRevote = false,
        public bool $anonymous = true,
    ) {
    }

    /**
     * Параметры для формы правки статьи.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'question' => $this->question,
            'options' => $this->options->values()->all(),
            'multiple' => $this->multiple,
            'max_choices' => $this->maxChoices,
            'ends_at' => $this->endsAt?->format(DATE_ATOM),
            'allow_revote' => $this->allowRevote,
            'anonymous' => $this->anonymous,
        ];
    }
}
