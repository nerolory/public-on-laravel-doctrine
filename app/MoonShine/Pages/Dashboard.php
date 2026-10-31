<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\Admin\AdminMetricsService;
use Illuminate\Contracts\View\Factory as ViewFactory;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Главная страница админки: ключевые показатели сайта.
 */
final class Dashboard extends Page
{
    /**
     * @param CoreContract        $core    ядро MoonShine
     * @param AdminMetricsService $metrics показатели
     * @param ViewFactory         $views   шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly AdminMetricsService $metrics,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core);
    }

    /**
     * Хлебные крошки: только сама страница.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.dashboard.title');
    }

    /**
     * Карточки показателей и топ авторов.
     *
     * @return iterable<FlexibleRender>
     */
    protected function components(): iterable
    {
        return [FlexibleRender::make($this->views->make('admin.metrics', ['metrics' => $this->metrics->get()]))];
    }
}
