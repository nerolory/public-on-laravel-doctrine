<?php

declare(strict_types=1);

namespace App\Enums\Media;

/**
 * Вид графика в статье.
 */
enum ChartType: string
{
    /** Столбчатая диаграмма. */
    case Bar = 'bar';

    /** Линейный график. */
    case Line = 'line';

    /** Круговая диаграмма. */
    case Pie = 'pie';
}
