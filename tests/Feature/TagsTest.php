<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Article;
use App\Entities\Role;
use App\Entities\Tag;
use Tests\DoctrineTestCase;

/**
 * Теги статей: назначение автором и модератором, проверка, подсказки и вывод на странице статьи.
 */
final class TagsTest extends DoctrineTestCase
{
    /**
     * Существующий тег переиспользуется без учёта регистра, регистр первого автора сохраняется.
     */
    public function test_author_assigns_new_and_existing_tags_case_insensitively(): void
    {
        $this->em->persist(Tag::create('PHP'));
        $this->em->flush();
        $this->actingAs($this->createUser('author@example.test'));

        $this->postJson('/api/articles', [
            'title' => 'Теги', 'content' => 'Текст', 'tags' => ['php', '  Doctrine   ORM ', 'C#'],
        ])->assertCreated();

        $names = $this->em->getConnection()->fetchFirstColumn('SELECT name FROM tags ORDER BY name');
        self::assertSame(
            ['C#', 'Doctrine ORM', 'PHP'],
            $names,
            'Существующий тег переиспользован, регистр первого автора сохранён.',
        );
        $id = $this->articleIdByTitle('Теги');
        $this->getJson('/api/my/articles/' . $id)->assertOk()->assertJsonPath('tags', ['C#', 'Doctrine ORM', 'PHP']);
    }

    /**
     * Слишком много тегов, повторы, теги с тегами разметки и слишком длинные теги отклоняются.
     */
    public function test_tags_are_validated(): void
    {
        $this->actingAs($this->createUser('author@example.test'));
        $tooMany = array_map(static fn (int $i): string => 'тег ' . $i, range(1, 11));

        $this->postJson('/api/articles', ['title' => 'А', 'content' => 'Б', 'tags' => $tooMany])
            ->assertJsonValidationErrors('tags');
        $this->postJson('/api/articles', ['title' => 'А', 'content' => 'Б', 'tags' => ['PHP', 'php']])
            ->assertJsonValidationErrors('tags.1');
        $this->postJson('/api/articles', ['title' => 'А', 'content' => 'Б', 'tags' => ['<script>']])
            ->assertJsonValidationErrors('tags.0');
        $this->postJson('/api/articles', ['title' => 'А', 'content' => 'Б', 'tags' => [str_repeat('я', 41)]])
            ->assertJsonValidationErrors('tags.0');
    }

    /**
     * Модератор задаёт теги строкой через запятую; правка заменяет прежний набор тегов.
     */
    public function test_moderator_sets_tags_as_comma_separated_text_and_edit_replaces_them(): void
    {
        $this->actingAsRole(Role::MODERATOR);
        $this->post('/admin/content/articles', [
            'title' => 'Админ', 'content' => 'Текст', 'tags' => 'Laravel, Doctrine, , laravel',
        ])->assertSessionHasErrors('tags.2');
        $this->post('/admin/content/articles', [
            'title' => 'Админ', 'content' => 'Текст', 'tags' => 'Laravel, Doctrine',
        ])->assertSessionHasNoErrors();
        $id = $this->articleIdByTitle('Админ');
        $this->get('/admin/page/article-form?id=' . $id)->assertOk()->assertSee('Doctrine, Laravel');

        $version = $this->articleVersion($id);
        $this->put('/admin/content/articles/' . $id, [
            'title' => 'Админ', 'content' => 'Текст', 'tags' => 'Symfony', 'version' => $version,
        ])->assertSessionHasNoErrors();
        $this->em->clear();
        self::assertSame(
            ['Symfony'],
            $this->em->find(Article::class, $id)->getTags()
                ->map(static fn (Tag $tag): string => $tag->getName())->all(),
        );
    }

    /**
     * Подсказки начинаются с введённого префикса и идут от популярных тегов; пустой запрос не проходит проверку.
     */
    public function test_suggestions_match_prefix_and_prefer_popular_tags(): void
    {
        $author = $this->createUser('author@example.test');
        [$laravel, $lazy, $vue] = [Tag::create('Laravel'), Tag::create('Lazy loading'), Tag::create('Vue')];
        foreach ([[$lazy], [$laravel, $vue], [$laravel]] as $i => $tags) {
            $article = Article::create('Статья ' . $i, 'Текст', $author);
            $article->replaceTags($tags);
            $this->em->persist($article);
        }
        foreach ([$laravel, $lazy, $vue] as $tag) {
            $this->em->persist($tag);
        }
        $this->em->flush();

        $this->getJson('/api/tags?q=la')->assertOk()->assertExactJson(['items' => ['Laravel', 'Lazy loading']]);
        $this->getJson('/api/tags?q=%25')->assertOk()->assertExactJson(['items' => []]);
        $this->getJson('/api/tags')->assertJsonValidationErrors('q');
    }

    /**
     * Теги опубликованной статьи выводятся в её карточке.
     */
    public function test_article_page_lists_tags(): void
    {
        $tag = Tag::create('Doctrine');
        $this->em->persist($tag);
        $article = $this->createArticle($this->createUser('author@example.test'), 'С тегами', 'Текст', publish: false);
        $article->replaceTags([$tag]);
        $article->publish();
        $this->em->flush();

        $this->getJson('/api/articles/' . $article->getId())->assertOk()->assertJsonPath('tags', ['Doctrine']);
    }
}
