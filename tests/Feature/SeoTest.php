<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Role;
use App\Entities\SeoMeta;
use App\Entities\Tag;
use App\Enums\Settings\SettingGroup;
use App\Services\Article\ArticleSectionService;
use App\Services\Seo\PublicFilesService;
use Database\Seeders\ArticlePublishedAtSeeder;
use Tests\DoctrineTestCase;

/**
 * SEO: заголовки и метатеги статей, правила индексации, robots.txt, карта сайта и RSS.
 */
final class SeoTest extends DoctrineTestCase
{
    /**
     * Заголовок и описание берутся из статьи по умолчанию; модератор переопределяет их.
     */
    public function test_article_head_uses_defaults_and_moderator_overrides(): void
    {
        $this->updateSettings(SettingGroup::Seo, ['seo__noindex' => '0']);
        $author = $this->createUser('author@example.test');
        $article = $this->createArticle(
            $author,
            'Заголовок',
            '<p>' . str_repeat('слово ', 50) . '</p>',
            publish: false,
        );
        $tag = Tag::create('Doctrine');
        $this->em->persist($tag);
        $article->replaceTags([$tag]);
        $article->publish();
        $this->em->flush();

        $head = $this->getJson('/api/articles/' . $article->getId())->assertOk()->json('head');
        self::assertSame('Заголовок — Слова и смыслы', $head['title']);
        $meta = $this->metaMap($head['meta']);
        self::assertStringEndsWith('…', $meta['description']);
        self::assertSame('Doctrine', $meta['keywords']);
        self::assertSame('article', $meta['og:type']);
        self::assertSame('Doctrine', $meta['article:tag']);
        self::assertArrayNotHasKey('robots', $meta);
        self::assertSame('BlogPosting', $head['json_ld'][0]['@type']);
        self::assertSame(
            ['rel' => 'canonical', 'href' => rtrim(config('app.url'), '/') . '/articles/' . $article->getId()],
            $head['link'][0],
        );

        $article->changeSeo(new SeoMeta('Свой заголовок', 'Своё описание'));
        $this->em->flush();
        $head = $this->getJson('/api/articles/' . $article->getId())->json('head');
        self::assertSame('Свой заголовок', $head['title']);
        self::assertSame('Своё описание', $this->metaMap($head['meta'])['description']);
    }

    /**
     * SEO-поля статьи от автора игнорируются; модератор сохраняет их.
     */
    public function test_only_moderators_change_seo_fields(): void
    {
        $author = $this->createUser('author@example.test');
        $this->actingAs($author);
        $this->postJson('/api/articles', ['title' => 'Автор', 'content' => '<p>Т</p>', 'seo_title' => 'Взлом SEO'])
            ->assertCreated();
        self::assertNull(
            $this->em->getConnection()->fetchOne('SELECT seo_title FROM articles WHERE title = ?', ['Автор']),
        );

        $this->actingAsRole(Role::MODERATOR);
        $this->post('/admin/content/articles', [
            'title' => 'Модератор', 'content' => '<p>Т</p>', 'seo_title' => 'Для поиска',
        ])->assertSessionHasNoErrors();
        self::assertSame(
            'Для поиска',
            $this->em->getConnection()->fetchOne('SELECT seo_title FROM articles WHERE title = ?', ['Модератор']),
        );
    }

    /**
     * По умолчанию сайт закрыт от индексации: robots.txt запрещает всё, метатег noindex стоит на главной и на статье.
     */
    public function test_noindex_is_on_by_default_everywhere(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSeeText("Disallow: /\n", escape: false);
        $meta = $this->metaMap($this->getJson('/api/head')->assertOk()->json('meta'));
        self::assertSame('noindex, nofollow', $meta['robots']);

        $article = $this->createArticle($this->createUser('author@example.test'), 'Закрытая', '<p>Т</p>');
        $articleMeta = $this->metaMap($this->getJson('/api/articles/' . $article->getId())->json('head.meta'));
        self::assertSame('noindex, nofollow', $articleMeta['robots']);
    }

    /**
     * Сидер дат публикации заполняет дату опубликованным статьям без неё, уже заполненные даты не меняет.
     */
    public function test_published_at_seeder_fills_missing_dates_once(): void
    {
        $author = $this->createUser('author@example.test');
        $missing = (int) $this->createArticle($author, 'Без даты')->getId();
        $filled = (int) $this->createArticle($author, 'С датой')->getId();
        $draft = (int) $this->createArticle($author, 'Черновик', publish: false)->getId();
        $connection = $this->em->getConnection();
        $connection->executeStatement(
            'UPDATE articles SET published_at = NULL, created_at = ? WHERE id = ?',
            ['2024-03-01 10:00:00', $missing],
        );
        $connection->executeStatement(
            'UPDATE articles SET published_at = ?, created_at = ? WHERE id = ?',
            ['2025-05-05 12:00:00', '2024-04-04 09:00:00', $filled],
        );

        $this->runSeeder(ArticlePublishedAtSeeder::class);
        $this->runSeeder(ArticlePublishedAtSeeder::class);

        $publishedAt = static fn (int $id): mixed => $connection
            ->fetchOne('SELECT published_at FROM articles WHERE id = ?', [$id]);
        self::assertSame('2024-03-01 10:00:00', $publishedAt($missing));
        self::assertSame('2025-05-05 12:00:00', $publishedAt($filled));
        self::assertNull($publishedAt($draft));
    }

    /**
     * Открытый сайт публикует robots.txt, карту сайта и RSS только с опубликованными статьями.
     */
    public function test_open_site_publishes_robots_sitemap_and_rss(): void
    {
        $this->updateSettings(SettingGroup::Seo, ['seo__noindex' => '0']);
        $article = $this->createArticle($this->createUser('author@example.test'), 'В карте', '<p>Т</p>');
        $draft = $this->createArticle($this->createUser('other@example.test'), 'Черновик', '<p>Т</p>', publish: false);
        $section = $this->app->make(ArticleSectionService::class)->create('Проза');

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: ' . rtrim(config('app.url'), '/') . '/sitemap.xml');
        // Карта пишется во временный файл, storage приложения не затрагивается.
        if (! is_dir($this->mediaRoot)) {
            mkdir($this->mediaRoot, 0777, recursive: true);
        }
        $path = $this->mediaRoot . '/sitemap.xml';
        $this->app->when(PublicFilesService::class)->needs('$sitemapPath')->give($path);
        $this->artisan('sitemap:generate')->assertSuccessful();
        $sitemap = (string) file_get_contents($path);
        self::assertStringContainsString('/articles/' . $article->getId() . '</loc>', $sitemap);
        self::assertStringNotContainsString('/articles/' . $draft->getId() . '</loc>', $sitemap);
        self::assertStringContainsString('/?section=' . $section->getSlug() . '</loc>', $sitemap);
        $this->get('/rss.xml')->assertOk()->assertSee('В карте')->assertDontSee('Черновик');
    }

    /**
     * Выключенная лента RSS отвечает 404.
     */
    public function test_disabled_rss_feed_is_not_found(): void
    {
        $this->updateSettings(SettingGroup::Seo, ['seo__rss_enabled' => '0']);

        $this->get('/rss.xml')->assertNotFound();
    }

    /**
     * Метатеги списком в виде карты «имя или свойство → значение».
     *
     * @param list<array<string, string>> $meta
     *
     * @return array<string, string>
     */
    private function metaMap(array $meta): array
    {
        $map = [];
        foreach ($meta as $tag) {
            $map[$tag['name'] ?? $tag['property']] = $tag['content'];
        }

        return $map;
    }
}
