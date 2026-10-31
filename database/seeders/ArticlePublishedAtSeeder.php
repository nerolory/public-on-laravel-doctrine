<?php

declare(strict_types=1);

namespace Database\Seeders;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Seeder;

/**
 * Дата публикации появилась позже самих статей. Опубликованным статьям без неё ставит дату создания —
 * она нужна для article:published_time, JSON-LD, карты сайта и RSS. Повторный запуск ничего не меняет.
 */
final class ArticlePublishedAtSeeder extends Seeder
{
    /**
     * Заполняет published_at у опубликованных статей, где дата пуста.
     */
    public function run(EntityManagerInterface $entityManager): void
    {
        $entityManager->getConnection()->executeStatement(
            'UPDATE articles SET published_at = created_at WHERE is_published = ? AND published_at IS NULL',
            [true],
            ['boolean'],
        );
    }
}
