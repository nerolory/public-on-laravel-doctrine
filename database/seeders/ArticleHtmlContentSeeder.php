<?php

declare(strict_types=1);

namespace Database\Seeders;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Seeder;
use UnexpectedValueException;

/**
 * Переводит статьи, написанные до появления редактора, из простого текста в HTML:
 * пустые строки разделяют абзацы, одиночные переносы становятся <br>. Статьи с HTML не трогает,
 * поэтому повторный запуск безопасен. Версия и журнал правок сохраняются, в записи меняется формат хранения текста.
 */
final class ArticleHtmlContentSeeder extends Seeder
{
    /**
     * Переводит в HTML статьи, содержимое которых начинается не с тега.
     */
    public function run(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        $rows = $connection->fetchAllAssociative("SELECT id, content FROM articles WHERE content NOT LIKE '<%'");
        foreach ($rows as $row) {
            $connection->executeStatement(
                'UPDATE articles SET content = ? WHERE id = ?',
                [self::toHtml((string) $row['content']), $row['id']],
            );
        }
    }

    /**
     * HTML из простого текста: абзацы по пустым строкам, переносы строк как <br>.
     *
     * @throws UnexpectedValueException текст содержит байты, не являющиеся UTF-8
     */
    private static function toHtml(string $text): string
    {
        $paragraphs = preg_split('/\R\s*\R/u', trim(str_replace("\r\n", "\n", $text)));
        if ($paragraphs === false) {
            throw new UnexpectedValueException('Текст статьи содержит некорректную кодировку UTF-8.');
        }

        return implode('', array_map(
            static fn (string $paragraph): string => '<p>'
                . preg_replace('/\R/u', '<br>', htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                . '</p>',
            array_filter($paragraphs, static fn (string $paragraph): bool => trim($paragraph) !== ''),
        ));
    }
}
