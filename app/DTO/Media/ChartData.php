<?php

declare(strict_types=1);

namespace App\DTO\Media;

use App\Enums\Media\ChartType;
use Illuminate\Support\Collection;

/**
 * Таблица для графика: тип, заголовок, подписи по оси X и ряды.
 */
final readonly class ChartData
{
    /**
     * @param ChartType                        $type   вид графика
     * @param string                           $title  заголовок
     * @param Collection<int, string>          $labels подписи по оси X
     * @param Collection<int, ChartSeriesData> $series ряды значений
     */
    public function __construct(
        public ChartType $type,
        public string $title,
        public Collection $labels,
        public Collection $series,
    ) {
    }

    /**
     * Подписи и ряды для JSON-колонки графика и ответа API.
     *
     * @return array{labels: list<string>, series: list<array{name: string, values: list<float>}>}
     */
    public function table(): array
    {
        return [
            'labels' => $this->labels->values()->all(),
            'series' => $this->series
                ->map(static fn (ChartSeriesData $series): array => $series->toArray())
                ->values()
                ->all(),
        ];
    }
}
