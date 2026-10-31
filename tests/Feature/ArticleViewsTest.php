<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\DoctrineTestCase;

/**
 * Счётчик просмотров: один просмотр в сутки с посетителя, без ботов, автора и черновиков.
 */
final class ArticleViewsTest extends DoctrineTestCase
{
    /** User-Agent обычного браузера: такой просмотр засчитывается. */
    private const string BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)'
        . ' Chrome/140.0 Safari/537.36';

    /**
     * Повторные просмотры за сутки, боты и автор не увеличивают счётчик; хранится только домен источника.
     */
    public function test_views_are_counted_once_a_day_without_bots_and_author(): void
    {
        $author = $this->createUser('author@example.test');
        $id = (int) $this->createArticle($author)->getId();
        $headers = ['User-Agent' => self::BROWSER, 'Referer' => 'https://ya.ru/search?text=секрет'];

        $this->post("/api/articles/{$id}/view", [], $headers)->assertNoContent();
        $this->post("/api/articles/{$id}/view", [], $headers)->assertNoContent();
        $this->post("/api/articles/{$id}/view", [], ['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->assertNoContent();
        $this->actingAs($author)->post("/api/articles/{$id}/view", [], ['User-Agent' => 'Mozilla/5.0 Firefox/140.0'])
            ->assertNoContent();

        self::assertSame(1, $this->viewCount($id));
        $row = $this->em->getConnection()
            ->fetchAssociative('SELECT visitor_hash, referrer_host, user_id FROM article_views');
        self::assertSame('ya.ru', $row['referrer_host'], 'Хранится только домен, без поискового запроса.');
        self::assertSame(64, strlen($row['visitor_hash']));
        self::assertStringNotContainsString('127.0.0.1', implode(' ', $row));

        $this->travel(1)->days();
        $this->asGuest();
        $this->post("/api/articles/{$id}/view", [], $headers)->assertNoContent();
        self::assertSame(2, $this->viewCount($id));
        // UPDATE счётчика обходит сущность в памяти, поэтому EntityManager очищается перед чтением.
        $this->em->clear();
        $this->getJson("/api/articles/{$id}")->assertJsonPath('view_count', 2);
    }

    /**
     * Просмотры черновиков не считаются; после очистки сырых данных счётчик статьи сохраняется.
     */
    public function test_unpublished_articles_are_not_counted_and_old_views_are_pruned(): void
    {
        $draft = (int) $this->createArticle($this->createUser('author@example.test'), publish: false)->getId();
        $this->post("/api/articles/{$draft}/view", [], ['User-Agent' => self::BROWSER])->assertNoContent();
        self::assertSame(0, $this->viewCount($draft));

        $id = (int) $this->createArticle($this->createUser('other@example.test'))->getId();
        $this->post("/api/articles/{$id}/view", [], ['User-Agent' => self::BROWSER])->assertNoContent();
        $this->travel(7)->months();
        $this->artisan('views:prune')->assertSuccessful();
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM article_views'));
        self::assertSame(1, $this->viewCount($id), 'Счётчик статьи остаётся после очистки сырых данных.');
    }

    /**
     * Число просмотров статьи из таблицы статей.
     */
    private function viewCount(int $id): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT view_count FROM articles WHERE id = ?', [$id]);
    }
}
