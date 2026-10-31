<?php

declare(strict_types=1);

namespace App\DTO\Media;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Сохранённый график: UUID и таблица данных.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ChartViewData implements Arrayable
{
    /**
     * @param string    $id    UUID графика
     * @param ChartData $chart тип, заголовок и таблица
     */
    public function __construct(
        public string $id,
        public ChartData $chart,
    ) {
    }

    /**
     * График для ответа API: UUID, тип, заголовок, подписи и ряды.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->chart->type->value,
            'title' => $this->chart->title,
            ...$this->chart->table(),
        ];
    }
}
