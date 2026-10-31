<?php

declare(strict_types=1);

namespace App\DTO\Media;

use Illuminate\Support\Collection;

/**
 * Ряд значений графика: название и значения по подписям.
 */
final readonly class ChartSeriesData
{
    /**
     * @param string                 $name   название ряда
     * @param Collection<int, float> $values значения по порядку подписей
     */
    public function __construct(
        public string $name,
        public Collection $values,
    ) {
    }

    /**
     * Значение по номеру подписи; недостающее считается нулём.
     */
    public function valueAt(int $index): float
    {
        return (float) $this->values->get($index, 0.0);
    }

    /**
     * Ряд для JSON-колонки графика и ответа API.
     *
     * @return array{name: string, values: list<float>}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'values' => $this->values->values()->all()];
    }
}
