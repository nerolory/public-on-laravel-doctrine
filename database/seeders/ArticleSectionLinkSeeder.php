<?php

declare(strict_types=1);

namespace Database\Seeders;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Seeder;

/**
 * Переносит раздел статьи из articles.section_id в таблицу связей.
 * Запускается между миграцией, создающей article_section_links, и миграцией, удаляющей articles.section_id.
 */
final class ArticleSectionLinkSeeder extends Seeder
{
    /**
     * Копирует пары статья — раздел, которых ещё нет в таблице связей.
     */
    public function run(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        if (! $connection->createSchemaManager()->introspectTableByUnquotedName('articles')->hasColumn('section_id')) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            INSERT INTO article_section_links (article_id, section_id)
            SELECT a.id, a.section_id FROM articles a
            WHERE a.section_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM article_section_links l
                  WHERE l.article_id = a.id AND l.section_id = a.section_id
              )
            SQL);
    }
}
