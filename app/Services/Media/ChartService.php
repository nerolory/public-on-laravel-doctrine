<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\DTO\Media\ChartData;
use App\DTO\Media\ChartViewData;
use App\Entities\Chart;
use App\Entities\User;
use App\Repositories\ChartRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Графики для статей: сервер хранит таблицу данных, рисует график фронтенд.
 */
final class ChartService
{
    /**
     * @param ChartRepository $charts графики
     */
    public function __construct(
        private readonly ChartRepository $charts,
    ) {
    }

    /**
     * Сохраняет новый график.
     */
    public function create(ChartData $data, User $owner): Chart
    {
        $chart = Chart::create((string) Str::uuid(), $data, $owner);
        $this->charts->save($chart);

        return $chart;
    }

    /**
     * График для ответа API: UUID, тип, заголовок и таблица.
     */
    public function present(Chart $chart): ChartViewData
    {
        return new ChartViewData(id: $chart->getUuid(), chart: $chart->chart());
    }

    /**
     * Графики по UUID для показа статьи.
     *
     * @param Collection<int, string> $uuids
     *
     * @return Collection<string, ChartViewData> по UUID
     */
    public function presentMany(Collection $uuids): Collection
    {
        return $this->charts->findByUuids($uuids)->map(fn (Chart $chart): ChartViewData => $this->present($chart));
    }
}
