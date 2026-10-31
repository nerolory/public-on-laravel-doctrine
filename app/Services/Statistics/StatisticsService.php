<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\DTO\Media\ChartData;
use App\DTO\Media\ChartSeriesData;
use App\DTO\Statistics\StatisticsReportData;
use App\Enums\Media\ChartType;
use App\Repositories\StatisticsRepository;
use App\Support\Settings\PageSizes;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Статистика для админки за выбранный период: итоги, данные графиков по дням и самое читаемое.
 */
final class StatisticsService
{
    /** Периоды отчёта в днях: неделя, месяц, квартал и полгода. */
    public const array PERIODS = [7, 30, 90, 180];

    /** Период отчёта, если в адресе страницы статистики не указан days: месяц из PERIODS. */
    public const int DEFAULT_PERIOD = 30;

    /** Формат дня в подписях графиков. */
    private const string DAY_FORMAT = 'd.m';

    /** Показатели графика посещаемости. */
    private const array TRAFFIC = ['views', 'visitors'];

    /** Показатели графика активности. */
    private const array ACTIVITY = ['registrations', 'articles', 'comments'];

    /**
     * @param StatisticsRepository $statistics агрегаты из базы
     * @param Translator           $translator подписи графиков
     * @param PageSizes            $pageSizes  длина топов
     */
    public function __construct(
        private readonly StatisticsRepository $statistics,
        private readonly Translator $translator,
        private readonly PageSizes $pageSizes,
    ) {
    }

    /**
     * Отчёт за период в днях из PERIODS.
     *
     * @throws InvalidArgumentException период не из списка PERIODS
     */
    public function report(int $days): StatisticsReportData
    {
        if (! in_array($days, self::PERIODS, strict: true)) {
            throw new InvalidArgumentException('Unsupported statistics period: ' . $days);
        }

        $today = CarbonImmutable::today();
        $dates = collect(range($days - 1, 0))->map(static fn (int $ago): CarbonImmutable => $today->subDays($ago));
        $from = $dates->first()->toDateString();
        $series = $this->series($dates, $from);
        $axis = $dates->map(static fn (CarbonImmutable $day): string => $day->format(self::DAY_FORMAT))->values();
        $limit = $this->pageSizes->statisticsTop();

        return new StatisticsReportData(
            period: $days,
            totals: $series->map(static fn (Collection $values): int => (int) $values->sum()),
            traffic: $this->chart('chart_traffic', $axis, $series->only(self::TRAFFIC)),
            activity: $this->chart('chart_activity', $axis, $series->only(self::ACTIVITY)),
            topArticles: $this->statistics->topArticles($from, $limit),
            topReferrers: $this->statistics->topReferrers($from, $limit),
        );
    }

    /**
     * Значения показателей по дням периода; дни без записей — нули.
     *
     * @param Collection<int, CarbonImmutable> $dates
     *
     * @return Collection<string, Collection<int, float>>
     */
    private function series(Collection $dates, string $from): Collection
    {
        $keys = $dates->map(static fn (CarbonImmutable $day): string => $day->toDateString());
        $views = $this->statistics->viewsByDay($from);
        $counts = collect([
            'registrations' => $this->statistics->registrationsByDay($from),
            'articles' => $this->statistics->publishedArticlesByDay($from),
            'comments' => $this->statistics->commentsByDay($from),
        ]);

        // Посещаемость берётся из записей просмотров за день.
        $traffic = collect([
            'views' => $keys->map(static fn (string $key): float => (float) ($views->get($key)?->views ?? 0)),
            'visitors' => $keys->map(static fn (string $key): float => (float) ($views->get($key)?->visitors ?? 0)),
        ]);

        // Счётчики по дням приводятся к ряду по всем дням периода: дни без записей остаются нулями.
        $activity = $counts->map(static fn (Collection $byDay): Collection => $keys->map(
            static fn (string $key): float => (float) $byDay->get($key, 0),
        ));

        return $traffic->merge($activity);
    }

    /**
     * Линейный график по показателям: подписи — дни периода.
     *
     * @param Collection<int, string>                    $axis
     * @param Collection<string, Collection<int, float>> $series
     */
    private function chart(string $title, Collection $axis, Collection $series): ChartData
    {
        $lines = $series
            ->map(fn (Collection $values, string $metric): ChartSeriesData => new ChartSeriesData(
                $this->label($metric),
                $values,
            ))
            ->values();

        return new ChartData(ChartType::Line, $this->label($title), $axis, $lines);
    }

    /**
     * Подпись из переводов admin.statistics.
     */
    private function label(string $key): string
    {
        return (string) $this->translator->get('admin.statistics.' . $key);
    }
}
