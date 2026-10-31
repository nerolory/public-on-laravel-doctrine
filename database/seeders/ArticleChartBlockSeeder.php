<?php

declare(strict_types=1);

namespace Database\Seeders;

use Doctrine\ORM\EntityManagerInterface;
use Dom\HTMLDocument;
use Illuminate\Database\Seeder;

/**
 * Переводит графики в тексте статей в блоки figure со ссылкой data-chart: картинка графика со ссылкой
 * на UUID убирается, ссылка переносится на figure. Статьи без таких картинок не трогает, поэтому повторный
 * запуск безопасен. Версия и журнал правок сохраняются, в записи меняется формат хранения графиков.
 */
final class ArticleChartBlockSeeder extends Seeder
{
    /**
     * Переводит статьи, в тексте которых есть картинки графиков.
     */
    public function run(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        $rows = $connection->fetchAllAssociative(
            "SELECT id, content FROM articles WHERE content LIKE '%<img%data-chart%'",
        );
        foreach ($rows as $row) {
            $connection->executeStatement(
                'UPDATE articles SET content = ? WHERE id = ?',
                [self::convert((string) $row['content']), $row['id']],
            );
        }
    }

    /**
     * HTML статьи с графиками в виде блоков: figure[data-type=chart][data-chart] и подпись.
     */
    private static function convert(string $html): string
    {
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
        );
        foreach ($document->body->querySelectorAll('img[data-chart]') as $image) {
            $figure = $image->closest('figure');
            if ($figure === null) {
                $figure = $document->createElement('figure');
                $image->before($figure);
            }
            $figure->setAttribute('data-type', 'chart');
            $figure->setAttribute('data-chart', (string) $image->getAttribute('data-chart'));
            $image->remove();
        }

        return $document->body->innerHTML;
    }
}
