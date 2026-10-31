<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Article;
use App\Entities\ArticleLogEntry;
use App\Entities\Role;
use Tests\DoctrineTestCase;

/**
 * Мои статьи: видимость черновиков, причины отклонения, фильтры, правки с версиями, удаление и лента.
 */
final class MyArticlesTest extends DoctrineTestCase
{
    /**
     * Черновик видит автор и модератор, остальные получают 404.
     */
    public function test_unpublished_article_is_visible_only_to_author_and_moderators(): void
    {
        $author = $this->createUser('author@example.test');
        $id = (int) $this->createArticle($author, 'Черновик', publish: false)->getId();

        $this->getJson('/api/articles/' . $id)->assertNotFound();
        $this->actingAs($this->createUser('reader@example.test'));
        $this->getJson('/api/articles/' . $id)->assertNotFound();
        $this->actingAs($author);
        $this->getJson('/api/articles/' . $id)->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('author.name', 'author')
            ->assertJsonPath('permissions', ['edit' => true, 'delete' => true, 'moderate' => false, 'react' => false]);
        $this->actingAsRole(Role::MODERATOR);
        $this->getJson('/api/articles/' . $id)->assertOk()
            ->assertJsonPath('permissions', ['edit' => false, 'delete' => false, 'moderate' => true, 'react' => false]);
    }

    /**
     * Автор видит причину отклонения в своём списке, читатели опубликованной статьи её не получают.
     */
    public function test_rejection_reason_is_shown_to_author_but_not_to_readers(): void
    {
        $author = $this->createUser('author@example.test');
        $article = $this->createArticle($author, 'Статья', publish: false);
        $id = $article->persistedId();
        $article->reject('Нет источников');
        $this->em->flush();

        $this->actingAs($author);
        $this->getJson('/api/my/articles')->assertOk()
            ->assertJsonPath('items.0.status', 'rejected')
            ->assertJsonPath('items.0.rejection_reason', 'Нет источников')
            ->assertJsonPath('counts.rejected', 1);

        $article->publish();
        $this->em->flush();
        $this->actingAs($this->createUser('reader@example.test'));
        $this->getJson('/api/articles/' . $id)->assertOk()
            ->assertJsonPath('rejection_reason', null)
            ->assertJsonPath('permissions.edit', false);
    }

    /**
     * Список автора фильтруется по статусу, анонс берётся из описания или начала текста, чужие статьи не видны.
     */
    public function test_my_articles_are_filtered_by_status_and_use_excerpt(): void
    {
        $author = $this->createUser('author@example.test');
        $published = (int) $this->createArticle($author, 'Опубликованная', str_repeat('слово ', 60))->getId();
        $this->createArticle($author, 'На модерации', 'Короткий текст', publish: false, summary: 'Своё описание');
        $this->createArticle($this->createUser('other@example.test'), 'Чужая', publish: false);

        $this->actingAs($author);
        $all = $this->getJson('/api/my/articles')->assertOk()
            ->assertJsonPath('counts', ['all' => 2, 'published' => 1, 'rejected' => 0, 'pending' => 1])
            ->json('items');
        self::assertSame(['На модерации', 'Опубликованная'], array_column($all, 'title'));
        self::assertSame('Своё описание', $all[0]['excerpt']);
        self::assertSame(
            Article::SUMMARY_LENGTH,
            mb_strlen($all[1]['excerpt']),
            'Без описания анонс — начало текста с многоточием на всю длину описания.',
        );
        self::assertStringEndsWith('…', $all[1]['excerpt']);
        self::assertArrayNotHasKey('content', $all[1]);

        $this->getJson('/api/my/articles?status=published')->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $published);
        $this->getJson('/api/my/articles?status=archived')->assertJsonValidationErrors('status');
    }

    /**
     * Правка автора возвращает статью на модерацию, сохраняет историю и отклоняет устаревшую версию.
     */
    public function test_author_edit_sends_article_back_to_moderation_and_keeps_history(): void
    {
        $author = $this->createUser('author@example.test');
        $id = (int) $this->createArticle($author, 'Было', 'Старый текст')->getId();

        $this->actingAs($author);
        $form = $this->getJson('/api/my/articles/' . $id)->assertOk()->assertJsonPath('title', 'Было')->json();
        $this->putJson('/api/my/articles/' . $id, [
            'title' => 'Стало', 'content' => 'Новый текст', 'summary' => 'Анонс', 'version' => $form['version'],
        ])->assertOk();

        $this->em->clear();
        $article = $this->em->find(Article::class, $id);
        self::assertSame('Стало', $article->getTitle());
        self::assertSame('pending', $article->status()->value);
        self::assertSame($form['version'] + 1, $article->getVersion());
        $titles = array_map(
            static fn (ArticleLogEntry $entry): ?string => $entry->getData()['title'] ?? null,
            $this->em->getRepository(ArticleLogEntry::class)->getLogEntries($article),
        );
        self::assertSame(['Стало', 'Было'], array_values(array_filter($titles)));

        $this->putJson('/api/my/articles/' . $id, [
            'title' => 'Устаревшая правка', 'content' => 'Текст', 'version' => $form['version'],
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'version_conflict');
        $this->putJson('/api/my/articles/' . $id, ['title' => 'Без версии', 'content' => 'Текст'])
            ->assertJsonValidationErrors('version');
    }

    /**
     * Чужую статью автор не видит, не меняет и не удаляет.
     */
    public function test_author_cannot_touch_someone_elses_article(): void
    {
        $id = (int) $this->createArticle($this->createUser('author@example.test'), 'Чужая', publish: false)->getId();
        $this->actingAs($this->createUser('other@example.test'));

        $this->getJson('/api/my/articles/' . $id)->assertNotFound();
        $this->putJson('/api/my/articles/' . $id, ['title' => 'Взлом', 'content' => 'Текст', 'version' => 1])
            ->assertNotFound();
        $this->deleteJson('/api/my/articles/' . $id, ['reason' => 'Хочу'])->assertNotFound();
        $this->em->clear();
        self::assertSame('Чужая', $this->em->find(Article::class, $id)->getTitle());
    }

    /**
     * Удаление требует причину; статья уходит в архив и перестаёт быть опубликованной.
     */
    public function test_author_deletes_article_to_archive_with_reason(): void
    {
        $author = $this->createUser('author@example.test');
        $id = (int) $this->createArticle($author, 'Удаляемая')->getId();
        $this->actingAs($author);

        $this->deleteJson('/api/my/articles/' . $id)->assertJsonValidationErrors('reason');
        $this->deleteJson('/api/my/articles/' . $id, ['reason' => 'Устарела'])->assertOk();

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT deleted_at, deletion_reason, is_published FROM articles WHERE id = ?',
            [$id],
        );
        self::assertNotNull($row['deleted_at']);
        self::assertSame('Устарела', $row['deletion_reason']);
        self::assertFalse((bool) $row['is_published']);
        $this->getJson('/api/articles/' . $id)->assertNotFound();
        $this->getJson('/api/my/articles')->assertJsonPath('counts.all', 0);
    }

    /**
     * Статья удалённого автора остаётся доступной читателям, но без данных автора.
     */
    public function test_article_of_deleted_author_stays_readable_without_author(): void
    {
        $author = $this->createUser('author@example.test');
        $id = (int) $this->createArticle($author, 'Наследие')->getId();
        $this->em->getConnection()->executeStatement(
            'UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$author->getAuthIdentifier()],
        );
        $this->em->clear();

        $this->getJson('/api/articles/' . $id)->assertOk()
            ->assertJsonPath('title', 'Наследие')
            ->assertJsonPath('author', null);
    }

    /**
     * В общей ленте статья выводится анонсом без полного текста.
     */
    public function test_public_feed_returns_excerpt_instead_of_full_text(): void
    {
        $this->createArticle(
            $this->createUser('author@example.test'),
            'Лента',
            '<p>Первый&nbsp;абзац</p><p>второй</p>',
        );

        $item = $this->getJson('/api/articles')->assertOk()->json('items.0');
        self::assertSame('Первый абзац второй', $item['excerpt']);
        self::assertArrayNotHasKey('content', $item);
    }
}
