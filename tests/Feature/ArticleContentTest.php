<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\ArticleChartBlockSeeder;
use Database\Seeders\ArticleHtmlContentSeeder;
use Illuminate\Testing\TestResponse;
use Tests\DoctrineTestCase;

/**
 * Содержимое статей: очистка HTML, картинки и обложка, графики и перевод старых текстов в HTML.
 */
final class ArticleContentTest extends DoctrineTestCase
{
    /**
     * HTML очищается, в статье остаются только картинки автора по UUID.
     */
    public function test_content_is_sanitized_and_keeps_only_own_images(): void
    {
        $author = $this->createUser('author@example.test');
        $this->actingAs($this->createUser('stranger@example.test'));
        $foreign = $this->uploadImage('content');
        $this->actingAs($author);
        $own = $this->uploadImage('content');

        $html = '<p onclick="steal()">Текст<script>alert(1)</script> <a href="javascript:alert(1)">плохая</a> '
            . '<a href="https://example.com" target="_blank">ссылка</a></p>'
            . '<figure data-type="image">'
            . '<img data-image="' . $own . '" src="https://evil.test/x.png" alt="Своя"></figure>'
            . '<figure data-type="image"><img data-image="' . $foreign . '" alt="Чужая"></figure>'
            . '<img src="https://evil.test/hotlink.png">';
        $this->postJson('/api/articles', ['title' => 'HTML', 'content' => $html])->assertCreated();

        $stored = (string) $this->em->getConnection()
            ->fetchOne('SELECT content FROM articles WHERE title = ?', ['HTML']);
        self::assertStringNotContainsString('script', $stored);
        self::assertStringNotContainsString('onclick', $stored);
        self::assertStringNotContainsString('javascript:', $stored);
        self::assertStringNotContainsString('evil.test', $stored, 'Адреса картинок не хранятся: только UUID.');
        self::assertStringNotContainsString($foreign, $stored);
        self::assertStringContainsString('data-image="' . $own . '"', $stored);
        self::assertStringContainsString('rel="noopener noreferrer nofollow ugc"', $stored);
        self::assertSame(1, substr_count($stored, '<figure'), 'Пустая обёртка чужой картинки удалена.');
    }

    /**
     * При показе статьи картинки получают адреса webp, srcset и размеры.
     */
    public function test_rendered_content_gets_image_urls_and_sizes(): void
    {
        $author = $this->createUser('author@example.test');
        $this->actingAs($author);
        $uuid = $this->uploadImage('content');
        $this->postJson('/api/articles', [
            'title' => 'С картинкой',
            'content' => '<p>Абзац</p><figure data-type="image"><img data-image="' . $uuid . '" alt="Фото"></figure>',
        ])->assertCreated();
        $id = $this->articleIdByTitle('С картинкой');

        $content = $this->getJson('/api/articles/' . $id)->assertOk()->json('content');
        self::assertMatchesRegularExpression('#<img [^>]*src="/media/content/[^"]+\.webp"#', $content);
        self::assertStringContainsString('srcset="', $content);
        self::assertStringContainsString('loading="lazy"', $content);
        self::assertStringContainsString('width="960"', $content);
        $this->getJson('/api/my/articles/' . $id)->assertOk()->assertJsonPath('content', $content);
    }

    /**
     * В статье не больше десяти картинок.
     */
    public function test_article_cannot_have_more_than_ten_images(): void
    {
        $this->actingAs($this->createUser('author@example.test'));
        $images = str_repeat('<img data-image="00000000-0000-4000-8000-000000000000">', 11);

        $this->postJson('/api/articles', ['title' => 'Много', 'content' => $images])
            ->assertJsonValidationErrors('content');
    }

    /**
     * Пустой редактор отклоняется; статья только с графиком сохраняется.
     */
    public function test_empty_editor_content_is_rejected(): void
    {
        $this->actingAs($this->createUser('author@example.test'));

        $this->postJson('/api/articles', ['title' => 'Пусто', 'content' => '<p></p><p>&nbsp;</p>'])
            ->assertJsonValidationErrors('content');
        $this->postJson('/api/articles', [
            'title' => 'Только график',
            'content' => '<figure data-type="chart" data-chart="00000000-0000-4000-8000-000000000000"></figure>',
        ])->assertCreated();
    }

    /**
     * Обложка должна быть своей картинкой назначения «обложка».
     */
    public function test_cover_must_be_own_cover_image(): void
    {
        $author = $this->createUser('author@example.test');
        $this->actingAs($this->createUser('stranger@example.test'));
        $foreign = $this->uploadImage('cover');
        $this->actingAs($author);
        $contentImage = $this->uploadImage('content');
        $cover = $this->uploadImage('cover');

        $this->postJson('/api/articles', ['title' => 'Чужая', 'content' => 'Текст', 'cover' => $foreign])
            ->assertJsonValidationErrors('cover');
        $this->postJson('/api/articles', ['title' => 'Не та', 'content' => 'Текст', 'cover' => $contentImage])
            ->assertJsonValidationErrors('cover');
        $this->postJson('/api/articles', ['title' => 'Обложка', 'content' => 'Текст', 'cover' => $cover])
            ->assertCreated();
        $id = $this->articleIdByTitle('Обложка');
        $this->getJson('/api/my/articles/' . $id)->assertOk()->assertJsonPath('cover.id', $cover);
    }

    /**
     * Данные графика хранятся у автора; в статье график встраивается блоком, чужой график не вставляется.
     */
    public function test_chart_data_is_stored_and_embedded_by_owner_only(): void
    {
        $author = $this->createUser('author@example.test');
        $this->actingAs($author);
        $chart = $this->postChart('Продажи <script>')
            ->assertCreated()
            ->assertJsonPath('type', 'bar')
            ->assertJsonPath('labels', ['Янв', 'Фев'])
            ->assertJsonMissingPath('url')
            ->json();
        $this->getJson('/api/charts/' . $chart['id'])->assertOk()->assertJsonPath('series.1.values', [11, 14]);

        $id = $this->postChartArticle('График', $chart['id']);
        self::assertSame(
            '<figure data-type="chart" data-chart="' . $chart['id']
            . '"><figcaption>Продажи &lt;script&gt;</figcaption></figure>',
            $this->contentOf($id),
        );

        $this->actingAs($this->createUser('stranger@example.test'));
        $this->getJson('/api/charts/' . $chart['id'])->assertNotFound();
        $this->postJson('/api/articles', [
            'title' => 'Чужой график',
            'content' => '<p>Т</p><figure data-type="chart" data-chart="' . $chart['id'] . '"></figure>',
        ])->assertCreated();
        self::assertSame('<p>Т</p>', $this->contentOf($this->articleIdByTitle('Чужой график')));
    }

    /**
     * Читатели видят график таблицей данных; в редакторе автора сохраняется блок графика.
     */
    public function test_chart_is_rendered_as_table_for_readers_only(): void
    {
        $author = $this->createUser('author@example.test');
        $this->actingAs($author);
        $chart = $this->postChart('Продажи')->assertCreated()->json();
        $id = $this->postChartArticle('График', $chart['id']);

        $article = $this->getJson('/api/articles/' . $id)->assertOk()
            ->assertJsonPath('charts.' . $chart['id'] . '.labels', ['Янв', 'Фев'])
            ->json();
        self::assertStringContainsString(
            '<table class="chart-table"><thead><tr><td></td>'
            . '<th scope="col">2025</th><th scope="col">2026</th></tr></thead>',
            $article['content'],
        );
        self::assertStringContainsString(
            '<tr><th scope="row">Фев</th><td>12.5</td><td>14</td></tr>',
            $article['content'],
        );
        self::assertSame(
            '<figure data-type="chart" data-chart="' . $chart['id'] . '"><figcaption>Продажи</figcaption></figure>',
            $this->getJson('/api/my/articles/' . $id)->json('content'),
            'В редакторе автора сохраняется блок графика.',
        );
    }

    /**
     * Картинка графика в тексте статьи становится блоком графика: с подписью и без неё. Повторный запуск
     * сидера ничего не меняет.
     */
    public function test_chart_images_are_converted_to_chart_blocks(): void
    {
        $author = $this->createUser('author@example.test');
        $uuid = '00000000-0000-4000-8000-000000000000';
        $figureHtml = '<p>Т</p><figure data-type="chart"><img data-chart="' . $uuid . '" alt="x">'
            . '<figcaption>Подпись</figcaption></figure>';
        $imageHtml = '<p>Т</p><img data-chart="' . $uuid . '" alt="y">';
        $figureId = (int) $this->createArticle($author, 'С фигурой', $figureHtml, publish: false)->getId();
        $imageId = (int) $this->createArticle($author, 'Без фигуры', $imageHtml, publish: false)->getId();
        $versions = [$this->articleVersion($figureId), $this->articleVersion($imageId)];

        $this->runSeeder(ArticleChartBlockSeeder::class);
        $this->runSeeder(ArticleChartBlockSeeder::class);

        self::assertSame(
            '<p>Т</p><figure data-type="chart" data-chart="' . $uuid . '"><figcaption>Подпись</figcaption></figure>',
            $this->contentOf($figureId),
        );
        self::assertSame(
            '<p>Т</p><figure data-type="chart" data-chart="' . $uuid . '"></figure>',
            $this->contentOf($imageId),
        );
        self::assertSame($versions, [$this->articleVersion($figureId), $this->articleVersion($imageId)]);
    }

    /**
     * Таблица графика проверяется: типы графиков, число значений и число рядов.
     */
    public function test_chart_table_is_validated(): void
    {
        $this->actingAs($this->createUser('author@example.test'));

        $this->postJson('/api/charts', [
            'type' => 'pie', 'title' => 'Доли', 'labels' => ['А', 'Б'],
            'series' => [['name' => 'x', 'values' => [1, -2]]],
        ])->assertJsonValidationErrors('series.0.values.1');
        $this->postJson('/api/charts', [
            'type' => 'pie', 'title' => 'Доли', 'labels' => ['А'],
            'series' => [['name' => 'x', 'values' => [1]], ['name' => 'y', 'values' => [2]]],
        ])->assertJsonValidationErrors('series');
        $this->postJson('/api/charts', [
            'type' => 'line', 'title' => 'Ряд', 'labels' => ['А', 'Б'], 'series' => [['name' => 'x', 'values' => [1]]],
        ])->assertJsonValidationErrors('series.0.values');
        $this->postJson('/api/charts', [
            'type' => 'area', 'title' => 'Ряд', 'labels' => ['А'], 'series' => [['name' => 'x', 'values' => [1]]],
        ])->assertJsonValidationErrors('type');
    }

    /**
     * Текст без HTML превращается в абзацы; повторный запуск сидера ничего не меняет.
     */
    public function test_plain_text_articles_are_converted_to_paragraphs(): void
    {
        $author = $this->createUser('author@example.test');
        $id = (int) $this->createArticle($author, 'Старая', "Первый <абзац>\nстрока\n\n\nВторой\n", publish: false)
            ->getId();
        $version = $this->articleVersion($id);

        $this->runSeeder(ArticleHtmlContentSeeder::class);
        $this->runSeeder(ArticleHtmlContentSeeder::class);

        self::assertSame('<p>Первый &lt;абзац&gt;<br>строка</p><p>Второй</p>', $this->contentOf($id));
        self::assertSame($version, $this->articleVersion($id));
    }

    /**
     * Сохраняет график с заданным названием и двумя рядами, возвращает ответ API.
     */
    private function postChart(string $title): TestResponse
    {
        return $this->postJson('/api/charts', [
            'type' => 'bar', 'title' => $title, 'labels' => ['Янв', 'Фев'],
            'series' => [['name' => '2025', 'values' => [10, 12.5]], ['name' => '2026', 'values' => [11, 14]]],
        ]);
    }

    /**
     * Сохраняет статью с блоком графика и подписью «Любая»; возвращает идентификатор статьи.
     */
    private function postChartArticle(string $title, string $chartId): int
    {
        $figure = '<figure data-type="chart" data-chart="' . $chartId . '"><figcaption>Любая</figcaption></figure>';
        $this->postJson('/api/articles', ['title' => $title, 'content' => $figure])->assertCreated();

        return $this->articleIdByTitle($title);
    }

    /**
     * Текст статьи, как он хранится в базе.
     */
    private function contentOf(int $id): string
    {
        return (string) $this->em->getConnection()->fetchOne('SELECT content FROM articles WHERE id = ?', [$id]);
    }
}
