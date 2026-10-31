<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Album;
use App\Entities\Article;
use App\Entities\Role;
use App\Entities\User;
use App\Enums\Moderation\ModerationAction;
use App\Services\Album\AlbumService;
use Tests\DoctrineTestCase;

/**
 * Альбомы: фото своих загрузок, модерация, возврат правки на проверку и вставка альбома в статью.
 */
final class AlbumsTest extends DoctrineTestCase
{
    /**
     * В альбом принимаются только свои фото, не больше десяти штук.
     */
    public function test_album_accepts_only_own_album_photos_up_to_ten(): void
    {
        $this->actingAs($this->createUser('stranger@example.test'));
        $foreign = $this->uploadImage('album');
        $owner = $this->createUser('owner@example.test');
        $this->actingAs($owner);
        $own = $this->uploadImage('album');
        $content = $this->uploadImage('content');

        $this->postJson('/api/albums', ['title' => 'Чужое', 'items' => [['image' => $foreign]]])
            ->assertJsonValidationErrors('items.0.image');
        $this->postJson('/api/albums', ['title' => 'Не то', 'items' => [['image' => $content]]])
            ->assertJsonValidationErrors('items.0.image');
        $eleven = array_map(static fn (): array => ['image' => fake()->uuid()], range(1, 11));
        $this->postJson('/api/albums', ['title' => 'Много', 'items' => $eleven])->assertJsonValidationErrors('items');
        $this->postJson('/api/albums', ['title' => 'Отпуск', 'items' => [['image' => $own, 'alt' => 'Море']]])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('items.0.alt', 'Море');
    }

    /**
     * Альбом виден всем только после одобрения; правка возвращает его на модерацию.
     */
    public function test_album_is_public_only_after_moderation_and_edit_sends_it_back(): void
    {
        $owner = $this->createUser('owner@example.test');
        $album = $this->createAlbum($owner);

        $this->asGuest();
        $this->getJson('/api/albums/' . $album)->assertNotFound();
        $this->actingAs($owner);
        $this->getJson('/api/albums/' . $album)->assertOk()->assertJsonPath('permissions.edit', true);

        $this->rejectAlbum($owner, $album);
        $this->approveAlbum($album);
        $this->editAlbum($owner, $album);
    }

    /**
     * Действие удаления из модерации стирает альбом: сервис передаёт его хранилищу вместо изменения состояния.
     */
    public function test_moderation_delete_removes_album(): void
    {
        $owner = $this->createUser('owner@example.test');
        $album = $this->createAlbum($owner);

        $this->app->make(AlbumService::class)->moderate(collect([$album]), ModerationAction::Delete, reason: null);

        $this->em->clear();
        self::assertNull($this->em->getRepository(Album::class)->findOneBy(['uuid' => $album]));
    }

    /**
     * Альбом в статье сохраняется для владельца; гости видят его после публикации и одобрения.
     */
    public function test_album_embed_is_kept_for_owner_and_shown_once_published(): void
    {
        $owner = $this->createUser('owner@example.test');
        $album = $this->createAlbum($owner);
        $foreignAlbum = $this->createAlbum($this->createUser('stranger@example.test'));

        $article = $this->createAlbumArticle($owner, $album, $foreignAlbum);
        $article->publish();
        $this->em->flush();
        $this->asGuest();
        self::assertStringNotContainsString(
            'figure',
            $this->getJson('/api/articles/' . $article->getId())->json('content'),
            'Неопубликованный альбом не показывается.',
        );

        $this->approveAlbumInArticle($album, $article);
    }

    /**
     * Фото без описания попадает в статью без атрибута alt.
     */
    public function test_album_photo_without_description_has_no_alt_in_article(): void
    {
        $owner = $this->createUser('owner@example.test');
        $this->actingAs($owner);
        $photo = $this->uploadImage('album');
        $album = $this->postJson('/api/albums', ['title' => 'Без описания', 'items' => [['image' => $photo]]])
            ->assertCreated()
            ->json('id');
        $html = '<figure data-type="album" data-album="' . $album . '" data-layout="collage"></figure>';
        $this->postJson('/api/articles', ['title' => 'Фото без описания', 'content' => $html])->assertCreated();
        $article = $this->em->getRepository(Article::class)->findOneBy(['title' => 'Фото без описания']);
        $article->publish();
        $this->em->flush();

        $this->actingAsRole(Role::MODERATOR);
        $this->post('/admin/content/albums/moderate', ['ids' => [$album], 'action' => 'approve'])
            ->assertRedirect('/admin/page/albums');
        $this->asGuest();
        $content = $this->getJson('/api/articles/' . $article->getId())->json('content');
        self::assertStringContainsString('<img ', $content);
        self::assertStringNotContainsString('alt=', $content);
    }

    /**
     * Модератор отклоняет альбом с причиной; владелец видит причину в своём списке.
     */
    private function rejectAlbum(User $owner, string $album): void
    {
        $this->actingAsRole(Role::MODERATOR);
        $this->get('/admin/page/albums')->assertOk()->assertSee('Отпуск')
            ->assertSee('/build/admin/assets/admin-', escape: false)
            ->assertSee('data-moderation-status', escape: false);
        $this->post('/admin/content/albums/moderate', ['ids' => [$album], 'action' => 'reject'])
            ->assertSessionHasErrors('reason');
        $this->post('/admin/content/albums/moderate', ['ids' => [$album], 'action' => 'reject', 'reason' => 'Размыто'])
            ->assertRedirect('/admin/page/albums');
        $this->actingAs($owner);
        $this->getJson('/api/my/albums')->assertOk()
            ->assertJsonPath('items.0.status', 'rejected')
            ->assertJsonPath('items.0.rejection_reason', 'Размыто');
    }

    /**
     * Второй модератор одобряет альбом: гость видит его опубликованным, без причины отклонения.
     */
    private function approveAlbum(string $album): void
    {
        $this->actingAsRole(Role::MODERATOR, 'moderator2@example.test');
        $this->post('/admin/content/albums/moderate', ['ids' => [$album], 'action' => 'approve'])
            ->assertRedirect('/admin/page/albums');
        $this->asGuest();
        $this->getJson('/api/albums/' . $album)->assertOk()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('rejection_reason', null);
    }

    /**
     * Правка владельца возвращает альбом на модерацию; чужой пользователь не меняет и не удаляет его.
     */
    private function editAlbum(User $owner, string $album): void
    {
        $this->actingAs($owner);
        $photo = $this->uploadImage('album');
        $this->putJson('/api/albums/' . $album, ['title' => 'Отпуск 2', 'items' => [['image' => $photo]]])
            ->assertOk()
            ->assertJsonPath('status', 'pending');
        $this->actingAs($this->createUser('stranger@example.test'));
        $this->putJson('/api/albums/' . $album, ['title' => 'Взлом', 'items' => [['image' => $photo]]])
            ->assertNotFound();
        $this->deleteJson('/api/albums/' . $album)->assertNotFound();
    }

    /**
     * Создаёт статью с двумя альбомами: свой сохраняется, чужой удаляется при сохранении.
     */
    private function createAlbumArticle(User $owner, string $album, string $foreignAlbum): Article
    {
        $this->actingAs($owner);
        $html = '<p>Текст</p>'
            . '<figure data-type="album" data-album="' . $album . '" data-layout="collage"><img src="x"></figure>'
            . '<figure data-type="album" data-album="' . $foreignAlbum . '" data-layout="slider"></figure>';
        $this->postJson('/api/articles', ['title' => 'С альбомом', 'content' => $html])->assertCreated();
        $article = $this->em->getRepository(Article::class)->findOneBy(['title' => 'С альбомом']);
        self::assertSame(
            '<p>Текст</p><figure data-type="album" data-album="' . $album . '" data-layout="collage"></figure>',
            $article->getContent(),
        );

        return $article;
    }

    /**
     * Модератор одобряет альбом, и гости видят его в статье с подписью и изображением.
     */
    private function approveAlbumInArticle(string $album, Article $article): void
    {
        $this->actingAsRole(Role::MODERATOR);
        $this->post('/admin/content/albums/moderate', ['ids' => [$album], 'action' => 'approve'])
            ->assertRedirect('/admin/page/albums');
        self::assertSame(
            'published',
            $this->em->getConnection()->fetchOne('SELECT status FROM albums WHERE uuid = ?', [$album]),
        );
        $this->asGuest();
        $content = $this->getJson('/api/articles/' . $article->getId())->json('content');
        self::assertStringContainsString(
            'class="album album-collage"',
            $content,
            'Кеш HTML статьи сброшен после модерации альбома.',
        );
        self::assertStringContainsString('<figcaption>Отпуск</figcaption>', $content);
        self::assertMatchesRegularExpression('#<img [^>]*src="/media/album/#', $content);
    }
}
