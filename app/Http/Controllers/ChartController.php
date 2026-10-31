<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\Chart;
use App\Http\Requests\Media\StoreChartRequest;
use App\Services\Media\ChartService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Графики из редактора статьи: создание и загрузка таблицы для правки.
 */
final class ChartController extends Controller
{
    /**
     * @param ResponseFactory $responses  ответы
     * @param Redirector      $redirector перенаправления
     * @param ChartService    $charts     графики
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ChartService $charts,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Рисует и сохраняет новый график.
     */
    public function store(StoreChartRequest $request): JsonResponse
    {
        $chart = $this->charts->create($request->chartData(), $request->user());

        return $this->responses->json($this->charts->present($chart), Response::HTTP_CREATED);
    }

    /**
     * Исходная таблица графика для правки.
     */
    public function show(Chart $chart): JsonResponse
    {
        return $this->responses->json($this->charts->present($chart));
    }
}
