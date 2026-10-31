<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use App\DTO\Media\ChartData;
use App\DTO\Media\ChartSeriesData;
use App\Entities\Chart;
use App\Enums\Media\ChartType;
use App\Support\Settings\ContentLimits;
use App\Traits\RequiresActiveUser;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Таблица для графика: число подписей и рядов ограничено настройками, у круговой диаграммы ряд один.
 */
final class StoreChartRequest extends FormRequest
{
    use RequiresActiveUser;

    /** Число рядов у круговой диаграммы: доли одного ряда складываются в целое. */
    private const int PIE_SERIES_LIMIT = 1;

    /**
     * Правила проверки таблицы.
     *
     * @return array<string, mixed>
     */
    public function rules(ContentLimits $limits): array
    {
        return [
            'type' => ['required', Rule::enum(ChartType::class)],
            'title' => ['required', 'string', 'max:' . Chart::TITLE_LENGTH],
            'labels' => ['required', 'array', 'min:1', 'max:' . $limits->chartLabels()],
            'labels.*' => ['required', 'string', 'max:' . Chart::LABEL_LENGTH],
            'series' => [
                'required',
                'array',
                'min:1',
                'max:' . ($this->isPie() ? self::PIE_SERIES_LIMIT : $limits->chartSeries()),
            ],
            'series.*.name' => ['required', 'string', 'max:' . Chart::LABEL_LENGTH],
            'series.*.values' => ['required', 'array', 'size:' . $this->collect('labels')->count()],
            'series.*.values.*' => [
                'required',
                'numeric',
                'between:' . -Chart::VALUE_LIMIT . ',' . Chart::VALUE_LIMIT,
                $this->pieValue(...),
            ],
        ];
    }

    /**
     * Проверенная таблица без лишних пробелов в подписях.
     */
    public function chartData(): ChartData
    {
        $data = $this->validated();

        return new ChartData(
            type: ChartType::from($data['type']),
            title: trim($data['title']),
            labels: collect($data['labels'])->map(static fn (string $label): string => trim($label))->values(),
            series: collect($data['series'])->map(static fn (array $series): ChartSeriesData => new ChartSeriesData(
                name: trim($series['name']),
                values: collect($series['values'])->map(static fn (mixed $value): float => (float) $value)->values(),
            ))->values(),
        );
    }

    /**
     * У круговой диаграммы значения не могут быть отрицательными.
     */
    private function pieValue(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->isPie() && (float) $value < 0) {
            $fail(__('messages.charts.pie_negative'));
        }
    }

    /**
     * Выбрана ли круговая диаграмма.
     */
    private function isPie(): bool
    {
        return $this->input('type') === ChartType::Pie->value;
    }
}
