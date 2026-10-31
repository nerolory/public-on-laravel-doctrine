<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Article;
use App\Entities\Comment;
use App\Entities\Role;
use App\Enums\Settings\SettingGroup;
use Illuminate\Testing\TestResponse;
use Tests\DoctrineTestCase;

/**
 * Жалобы читателей: подача, очередь модератора, решения и автоматическое скрытие.
 */
final class ComplaintsTest extends DoctrineTestCase
{
    /** Названия статей очереди жалоб: по ним считается, сколько статей попало на страницу. */
    private const array TITLES = ['Жалобная 1', 'Жалобная 2', 'Жалобная 3', 'Жалобная 4', 'Жалобная 5', 'Жалобная 6'];

    /**
     * Жалобу подают только вошедшие пользователи, один раз и не на своё.
     */
    public function test_users_report_once_and_not_their_own_content(): void
    {
        $author = $this->createUser('author@example.test');
        $article = $this->createArticle($author);
        $articleId = $article->getId();
        $this->complain('article', $articleId, 'hate')->assertUnauthorized();

        $this->actingAs($author);
        $this->complain('article', $articleId, 'hate')->assertJsonValidationErrors('id');
        $this->complain('user', $author->getAuthIdentifier(), 'insult')->assertJsonValidationErrors('id');

        $this->actingAs($this->createUser('reader@example.test'));
        $this->complain('article', $articleId, 'other')->assertJsonValidationErrors('text');
        $this->complain('article', $articleId, 'spam')->assertJsonValidationErrors('reason');
        $this->complain('article', '999999', 'hate')->assertNotFound();
        $this->complain('article', $articleId, 'other', 'Реклама казино')->assertCreated();
        $this->complain('article', $articleId, 'hate')->assertJsonValidationErrors('id');
        $this->complain('user', $author->getAuthIdentifier(), 'insult')->assertCreated();
    }

    /**
     * Модератор видит жалобу на комментарий и скрывает комментарий: жалоба удовлетворена, открытых жалоб нет.
     */
    public function test_moderator_hides_comment_by_complaint(): void
    {
        $article = $this->createArticle($this->createUser('author@example.test'));
        $commenter = $this->createUser('commenter@example.test');
        $comment = Comment::post($article, $commenter, 'Грубость');
        $this->em->persist($comment);
        $this->em->flush();
        $this->actingAs($this->createUser('reader@example.test'));
        $this->complain('comment', $comment->getId(), 'insult')->assertCreated();

        $this->actingAsRole(Role::MODERATOR);
        $this->get('/admin/page/complaints')->assertOk()->assertSee('Грубость')->assertSee('Оскорбление: 1');
        $this->post('/admin/content/complaints/resolve', [
            'target' => 'comment', 'id' => $comment->getId(), 'action' => 'hide',
        ])->assertRedirect('/admin/page/complaints');
        $this->getJson("/api/articles/{$article->getId()}/comments")->assertJsonPath('total', 0);
        self::assertSame('upheld', $this->em->getConnection()->fetchOne('SELECT status FROM complaints'));
        $this->get('/admin/page/complaints')->assertOk()->assertSee('Открытых жалоб нет.');
    }

    /**
     * Модератор получает 403 при попытке заблокировать администратора через жалобу и отклоняет её.
     */
    public function test_moderator_cannot_ban_an_administrator_through_complaints(): void
    {
        $admin = $this->createUser('admin@example.test', 'Админ', Role::ADMINISTRATOR);
        $this->actingAs($this->createUser('reader@example.test'));
        $this->complain('user', $admin->getAuthIdentifier(), 'insult')->assertCreated();

        $this->actingAsRole(Role::MODERATOR);
        $this->post('/admin/content/complaints/resolve', [
            'target' => 'user', 'id' => $admin->getAuthIdentifier(), 'action' => 'hide',
        ])->assertForbidden();
        $this->post('/admin/content/complaints/resolve', [
            'target' => 'user', 'id' => $admin->getAuthIdentifier(), 'action' => 'dismiss',
        ])->assertRedirect('/admin/page/complaints');
        self::assertSame('dismissed', $this->em->getConnection()->fetchOne('SELECT status FROM complaints'));
    }

    /**
     * Решение без идентификатора объекта возвращает ошибку поля id.
     */
    public function test_resolve_without_target_id_is_a_field_error(): void
    {
        $this->actingAsRole(Role::MODERATOR);

        $this->postJson('/admin/content/complaints/resolve', ['target' => 'article', 'action' => 'hide'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id');
    }

    /**
     * Порог жалоб из настроек скрывает статью без участия модератора.
     */
    public function test_auto_hide_is_an_admin_option(): void
    {
        $this->updateSettings(SettingGroup::Content, [
            'content__complaints_auto_hide' => '1', 'content__complaints_threshold' => 2,
        ]);
        $article = $this->createArticle($this->createUser('author@example.test'));
        $articleId = $article->getId();

        foreach (['first', 'second'] as $i => $name) {
            $this->actingAs($this->createUser("{$name}@example.test"));
            $this->complain('article', $articleId, 'illegal')->assertCreated();
            $this->em->clear();
            self::assertSame($i === 1, $this->em->find(Article::class, $articleId)->status()->value === 'rejected');
        }
        $this->asGuest();
        $this->getJson('/api/articles/' . $articleId)->assertNotFound();
    }

    /**
     * Жалоба на альбом попадает в очередь; решение модератора «скрыть» отклоняет альбом.
     */
    public function test_album_complaint_is_queued_and_hidden_by_moderator(): void
    {
        $album = $this->createAlbum($this->createUser('owner@example.test'));
        $moderator = $this->actingAsRole(Role::MODERATOR);
        $this->post('/admin/content/albums/moderate', ['ids' => [$album], 'action' => 'approve'])
            ->assertRedirect('/admin/page/albums');
        $albumId = (int) $this->em->getConnection()->fetchOne('SELECT id FROM albums WHERE uuid = ?', [$album]);

        $this->actingAs($this->createUser('reader@example.test'));
        $this->postJson('/api/complaints', ['target' => 'album', 'id' => $album, 'reason' => 'hate'])->assertCreated();

        $this->actingAs($moderator);
        $this->get('/admin/page/complaints')->assertOk()
            ->assertSee('Отпуск')
            ->assertSee(__('admin.complaints.reason_names.hate') . ': 1');
        $this->post('/admin/content/complaints/resolve', ['target' => 'album', 'id' => $albumId, 'action' => 'hide'])
            ->assertRedirect('/admin/page/complaints');
        $this->asGuest();
        $this->getJson('/api/albums/' . $album)->assertNotFound();
    }

    /**
     * Очередь жалоб делится на страницы по настройке размера страницы админки.
     */
    public function test_complaint_queue_is_paginated(): void
    {
        $this->updateSettings(SettingGroup::Pagination, ['pagination__admin' => 5]);
        $author = $this->createUser('author@example.test');
        $this->actingAs($this->createUser('reader@example.test'));
        foreach (self::TITLES as $title) {
            $article = $this->createArticle($author, $title);
            $this->complain('article', $article->getId(), 'hate')->assertCreated();
        }

        $this->actingAsRole(Role::MODERATOR);
        $firstPage = $this->get('/admin/page/complaints')->assertOk()->assertSee('page=2', escape: false)->getContent();
        $secondPage = $this->get('/admin/page/complaints?page=2')->assertOk()->getContent();

        self::assertSame(5, $this->countTitlesIn($firstPage, self::TITLES));
        self::assertSame(1, $this->countTitlesIn($secondPage, self::TITLES));
    }

    /**
     * Подаёт жалобу от имени текущего пользователя; пояснение передаётся только если оно есть.
     */
    private function complain(string $target, int|string $id, string $reason, ?string $text = null): TestResponse
    {
        $payload = ['target' => $target, 'id' => (string) $id, 'reason' => $reason];
        if ($text !== null) {
            $payload['text'] = $text;
        }

        return $this->postJson('/api/complaints', $payload);
    }
}
