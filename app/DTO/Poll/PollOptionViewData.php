<?php

declare(strict_types=1);

namespace App\DTO\Poll;

use App\DTO\User\UserCardData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Вариант ответа с итогами и последними проголосовавшими.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PollOptionViewData implements Arrayable
{
    /**
     * @param int                           $id      идентификатор варианта
     * @param string                        $text    текст варианта
     * @param int                           $votes   число голосов
     * @param int                           $percent доля голосов в процентах
     * @param Collection<int, UserCardData> $voters  последние; в анонимном пусто
     * @param int                           $more    сверх показанных голосов
     */
    public function __construct(
        public int $id,
        public string $text,
        public int $votes,
        public int $percent,
        public Collection $voters,
        public int $more,
    ) {
    }

    /**
     * Вариант для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'votes' => $this->votes,
            'percent' => $this->percent,
            'voters' => $this->voters->toArray(),
            'more' => $this->more,
        ];
    }
}
