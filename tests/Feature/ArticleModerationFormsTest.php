<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Role;
use App\Enums\Settings\SettingGroup;
use Tests\DoctrineTestCase;

/**
 * Статьи и модерация: публичный API статей, управление автором и модератором, списки в админке и массовые действия.
 */
final class ArticleModerationFormsTest extends DoctrineTestCase
{
    /**
     * Опубликованная статья видна в списке и по идентификатору; несуществующая статья даёт 404.
     */
    public function test_public_article_endpoints(): void
    {
        $user = $this->createUser('alice@example.test', 'Alice');
        $this->em->getConnection()->insert('articles', [
            'title' => 'Test article', 'content' => 'Article content',
            'user_id' => $user->getAuthIdentifier(), 'is_published' => true,
        ]);
        $this->getJson('/api/articles')->assertOk()->assertJsonPath('items.0.title', 'Test article');
        $id = $this->articleIdByTitle('Test article');
        $this->getJson('/api/articles/' . $id)->assertOk()->assertJsonPath('content', 'Article content');
        $this->getJson('/api/articles/99999')->assertNotFound();
    }

    /**
     * Администратор с назначенной ролью создаёт, публикует, правит и удаляет статьи.
     */
    public function test_designated_admin_can_manage_articles(): void
    {
        $user = $this->createUser('alice@example.test', 'Alice');
        $this->actingAs($user);
        $this->grantRole($user, Role::ADMINISTRATOR);
        $this->post('/admin/content/articles', ['title' => 'First', 'content' => 'Content'])
            ->assertRedirect('/admin/page/articles');
        $id = $this->articleIdByTitle('First');
        self::assertGreaterThan(0, $id);
        $this->get('/admin/page/articles')->assertOk()->assertSee('First');
        $this->getJson('/api/articles/' . $id)->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('permissions.moderate', true);
        $this->post('/admin/content/articles/' . $id . '/publish')->assertRedirect('/admin/page/articles');
        $this->put('/admin/content/articles/' . $id, [
            'title' => 'Updated', 'content' => 'New content', 'version' => $this->articleVersion($id),
        ])->assertRedirect('/admin/page/articles');
        $this->getJson('/api/articles/' . $id)->assertJsonPath('title', 'Updated');
        $this->delete('/admin/content/articles/' . $id)->assertRedirect('/admin/page/articles');
        $this->getJson('/api/articles/' . $id)->assertNotFound();
    }

    /**
     * Автор отправляет черновик на модерацию; опубликовать его сам автор не может.
     */
    public function test_user_can_submit_a_draft_but_cannot_publish_it(): void
    {
        $user = $this->createUser('author@example.test', 'Author');
        $this->actingAs($user);
        $this->postJson('/api/articles', [
            'title' => 'Draft', 'content' => 'Text', 'is_published' => true,
        ])->assertCreated();
        $id = $this->articleIdByTitle('Draft');
        $this->getJson('/api/articles/' . $id)->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('permissions.edit', true)
            ->assertJsonPath('permissions.moderate', false);
        $this->post('/admin/content/articles/' . $id . '/publish')->assertForbidden();
        $published = $this->em->getConnection()->fetchOne('SELECT is_published FROM articles WHERE id = ?', [$id]);
        self::assertFalse((bool) $published);
    }

    /**
     * Список статей показывает автора и статус, делится на страницы по настройке размера страницы админки.
     */
    public function test_article_list_has_author_status_and_numbered_pagination(): void
    {
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $author = $this->createUser('author@example.test', 'Автор');
        $this->updateSettings(SettingGroup::Pagination, ['pagination__admin' => 12]);
        foreach ($this->titles() as $title) {
            $this->createArticle($author, $title, 'Краткое содержание', publish: false);
        }

        $first = $this->actingAs($moderator)->get('/admin/page/articles')->assertOk()
            ->assertSee('Создать статью')
            ->assertSee('Автор')
            ->assertSee('Краткое содержание')
            ->assertSee('На модерации')
            ->assertSee('Редактировать')
            ->assertSee('page=2', escape: false)
            ->assertSee('Выбрать действие')
            ->assertSee('Применить')
            ->getContent();
        $second = $this->get('/admin/page/articles?page=2')->assertOk()->getContent();

        self::assertSame(12, $this->countTitlesIn($first, $this->titles()));
        self::assertSame(1, $this->countTitlesIn($second, $this->titles()));
    }

    /**
     * Массовые действия модерации доступны модератору; автор получает 403, пустой список — ошибку поля ids.
     */
    public function test_moderation_bulk_actions_and_authorization(): void
    {
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $author = $this->createUser('author@example.test', 'Author');
        $this->createArticle($author, 'First', 'Text', publish: false);
        $this->createArticle($author, 'Second', 'Text', publish: false);
        $ids = $this->em->getConnection()->fetchFirstColumn('SELECT id FROM articles ORDER BY id');

        $this->actingAs($author)
            ->post('/admin/content/articles/moderate', ['ids' => $ids, 'action' => 'approve'])
            ->assertForbidden();
        $this->flushSession();
        $this->actingAs($moderator)->post('/admin/content/articles/moderate', ['action' => 'approve'])
            ->assertSessionHasErrors('ids');
        $this->actingAs($moderator)->post('/admin/content/articles/moderate', ['ids' => $ids, 'action' => 'approve'])
            ->assertRedirect('/admin/page/articles');
        $this->getJson('/api/articles/' . $ids[0])->assertOk();
        $this->post('/admin/content/articles/moderate', [
            'ids' => $ids, 'action' => 'reject', 'reason' => 'Нужны источники',
        ])->assertRedirect('/admin/page/articles');
        $this->getJson('/api/articles/' . $ids[0])->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('rejection_reason', 'Нужны источники');
        $this->em->clear();
        $row = $this->rowFlags($ids[0]);
        self::assertFalse((bool) $row['is_published']);
        self::assertTrue((bool) $row['is_rejected']);
        $this->post('/admin/content/articles/moderate', ['ids' => [$ids[0]], 'action' => 'pending'])
            ->assertRedirect('/admin/page/articles');
        $row = $this->rowFlags($ids[0]);
        self::assertFalse((bool) $row['is_published']);
        self::assertFalse((bool) $row['is_rejected']);
    }

    /**
     * Массовое удаление переносит статьи в архив; неизвестный id в списке отменяет всё действие.
     */
    public function test_bulk_delete_archives_articles(): void
    {
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $author = $this->createUser('author@example.test', 'Author');
        $this->createArticle($author, 'First', 'Text', publish: false);
        $this->createArticle($author, 'Second', 'Text', publish: false);
        $ids = $this->em->getConnection()->fetchFirstColumn('SELECT id FROM articles ORDER BY id');

        $this->actingAs($moderator);
        $this->post('/admin/content/articles/moderate', ['ids' => [$ids[0], 99999], 'action' => 'delete'])
            ->assertNotFound();
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM articles'));
        $this->post('/admin/content/articles/moderate', ['ids' => $ids, 'action' => 'delete'])
            ->assertRedirect('/admin/page/articles');
        self::assertSame(
            2,
            (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM articles WHERE deleted_at IS NOT NULL'),
            'Удаление переносит статьи в архив.',
        );
        $this->getJson('/api/articles/' . $ids[0])->assertNotFound();
    }

    /**
     * Модератор открывает форму новой статьи.
     */
    public function test_moderator_can_open_new_article_form(): void
    {
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $this->actingAs($moderator)->get('/admin/page/article-form')->assertOk()
            ->assertSee('Новая статья')
            ->assertSee('Заголовок')
            ->assertSee('Создать статью');
    }

    /**
     * В списке видны статусы «на модерации», «одобрено» и «отклонено» с причиной.
     */
    public function test_article_list_shows_moderation_statuses(): void
    {
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $this->createArticle($moderator, 'Approved', 'Text');
        $rejected = $this->createArticle($moderator, 'Rejected', 'Text', publish: false);
        $rejected->reject('Нужны источники');
        $this->createArticle($moderator, 'Pending', 'Text', publish: false);
        $this->em->flush();

        $this->actingAs($moderator)->get('/admin/page/articles')->assertOk()
            ->assertSee('На модерации')
            ->assertSee('Одобрено')
            ->assertSee('Отклонено')
            ->assertSee('Нужны источники')
            ->assertSee('reject-dialog', escape: false);
    }

    /**
     * Модератор открывает форму правки статьи.
     */
    public function test_moderator_can_open_edit_article_form(): void
    {
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $this->createArticle($moderator, 'Existing', 'Existing body', publish: false);
        $id = $this->articleIdByTitle('Existing');

        $this->actingAs($moderator)->get('/admin/page/article-form?id=' . $id)->assertOk()
            ->assertSee('Редактировать статью')
            ->assertSee('Existing')
            ->assertSee('PUT');
    }

    /**
     * Модератор публикует статью и получает 403 при попытке заблокировать автора.
     */
    public function test_moderator_can_publish_but_not_manage_users(): void
    {
        $author = $this->createUser('author@example.test', 'Author');
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $this->createArticle($author, 'Draft', 'Text', publish: false);
        $id = $this->articleIdByTitle('Draft');

        $this->actingAs($moderator)->post('/admin/content/articles/' . $id . '/publish')
            ->assertRedirect('/admin/page/articles');
        $this->getJson('/api/articles/' . $id)->assertOk();
        $this->post('/admin/content/users/' . $author->getAuthIdentifier() . '/ban')->assertForbidden();
    }

    /**
     * Флаги публикации и отклонения статьи из таблицы статей.
     *
     * @return array<string, mixed>
     */
    private function rowFlags(int $id): array
    {
        return $this->em->getConnection()
            ->fetchAssociative('SELECT is_published, is_rejected FROM articles WHERE id = ?', [$id]);
    }

    /**
     * Названия статей из списка с двузначными номерами: по ним считается число статей на странице.
     *
     * @return list<string>
     */
    private function titles(): array
    {
        return array_map(static fn (int $number): string => sprintf('Статья %02d', $number), range(1, 13));
    }
}
