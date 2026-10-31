<?php

declare(strict_types=1);

namespace App\Jobs\Seo;

use App\Services\Seo\PublicFilesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Пересборка карты сайта по кнопке в админке. Повторные нажатия, пока задача ждёт в очереди, её не дублируют.
 */
final class GenerateSitemapJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use Queueable;

    /**
     * Пересобирает карту сайта.
     */
    public function handle(PublicFilesService $files): void
    {
        $files->generateSitemap();
    }
}
