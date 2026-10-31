<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Seo\PublicFilesService;
use Illuminate\Console\Command;

/**
 * Пересобирает карту сайта; планировщик запускает её раз в сутки.
 */
final class GenerateSitemap extends Command
{
    /** Время ежедневного запуска: ночью, после очистки просмотров. */
    public const string DAILY_AT = '04:00';

    /** @var string имя и параметры команды */
    protected $signature = 'sitemap:generate';

    /** @var string описание в списке команд */
    protected $description = 'Пересобирает карту сайта sitemap.xml';

    /**
     * Пересобирает карту и выводит число адресов.
     */
    public function handle(PublicFilesService $files): int
    {
        $this->info('Адресов в карте сайта: ' . $files->generateSitemap());

        return self::SUCCESS;
    }
}
