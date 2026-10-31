<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Article\ArticleViewService;
use Illuminate\Console\Command;

/**
 * Удаляет сырые просмотры старше срока хранения из настроек; планировщик запускает её раз в сутки.
 */
final class PruneArticleViews extends Command
{
    /** Время ежедневного запуска: ночью, когда посетителей меньше всего. */
    public const string DAILY_AT = '03:30';

    /** @var string имя и параметры команды */
    protected $signature = 'views:prune';

    /** @var string описание в списке команд */
    protected $description = 'Удаляет сырые просмотры статей старше срока хранения';

    /**
     * Удаляет просмотры и выводит их число.
     */
    public function handle(ArticleViewService $views): int
    {
        $this->info('Удалено просмотров: ' . $views->prune());

        return self::SUCCESS;
    }
}
