<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Comment;
use App\Entities\Image;
use App\Entities\ImageDimensions;
use App\Entities\Role;
use App\Entities\User;
use App\Enums\Media\ImagePurpose;
use App\Enums\Media\StorageDriver;
use App\Repositories\CommentRepository;
use Illuminate\Support\Str;
use Tests\DoctrineTestCase;

/**
 * Комментарии: кто может писать, ответы одного уровня, правка и удаление, реакции и рейтинг конфликтов.
 */
final class CommentsTest extends DoctrineTestCase
{
    /**
     * Гость читает комментарии опубликованной статьи, но писать может только пользователь; черновик скрыт.
     */
    public function test_guests_read_but_only_users_comment_on_published_articles(): void
    {
        $article = (int) $this->createArticle($this->createUser('author@example.test'))->getId();
        $draft = (int) $this->createArticle($this->createUser('draft@example.test'), publish: false)->getId();

        $this->getJson("/api/articles/{$article}/comments")->assertOk()->assertJsonPath('total', 0);
        $this->postJson("/api/articles/{$article}/comments", ['body' => 'Привет'])->assertUnauthorized();
        $this->getJson("/api/articles/{$draft}/comments")->assertNotFound();

        $this->actingAs($this->createUser('reader@example.test'));
        $this->postJson("/api/articles/{$draft}/comments", ['body' => 'Привет'])->assertNotFound();
        $this->postJson("/api/articles/{$article}/comments", ['body' => str_repeat('я', 2001)])
            ->assertJsonValidationErrors('body');
        $this->postJson("/api/articles/{$article}/comments", ['body' => '  <b>Привет</b>  '])->assertCreated()
            ->assertJsonPath('body', '<b>Привет</b>')
            ->assertJsonPath('author.name', 'reader')
            ->assertJsonPath('permissions', ['edit' => true, 'delete' => true, 'reply' => true, 'react' => false]);
    }

    /**
     * Ответ можно дать только на корневой комментарий; ответ на ответ становится ответом на корень и помнит адресата.
     */
    public function test_replies_stay_one_level_deep_and_remember_the_addressee(): void
    {
        $article = (int) $this->createArticle($this->createUser('author@example.test'))->getId();
        $alice = $this->createUser('alice@example.test');
        $bob = $this->createUser('bob@example.test');
        $this->actingAs($alice);
        $root = $this->postJson("/api/articles/{$article}/comments", ['body' => 'Корень'])->json('id');
        $this->actingAs($bob);
        $reply = $this->postJson("/api/articles/{$article}/comments", ['body' => 'Ответ', 'reply_to' => $root])
            ->assertCreated()
            ->assertJsonPath('parent_id', $root)
            ->assertJsonPath('reply_to', null)
            ->json('id');
        $this->actingAs($alice);
        $this->postJson("/api/articles/{$article}/comments", ['body' => 'Ответ на ответ', 'reply_to' => $reply])
            ->assertCreated()
            ->assertJsonPath('parent_id', $root)
            ->assertJsonPath('reply_to.name', 'bob');

        $threads = $this->getJson("/api/articles/{$article}/comments")->assertOk()->json();
        self::assertSame(3, $threads['total']);
        self::assertCount(1, $threads['items']);
        self::assertSame(2, $threads['items'][0]['reply_count']);
        self::assertSame(
            ['Ответ', 'Ответ на ответ'],
            array_column($this->getJson("/api/comments/{$root}/replies")->assertOk()->json('items'), 'body'),
        );
        $this->getJson("/api/comments/{$reply}/replies")->assertNotFound();
    }

    /**
     * Править и удалять комментарий может автор, модератор удаляет чужой; корень с ответами остаётся заглушкой.
     */
    public function test_edit_by_author_delete_by_author_or_moderator_keeps_thread(): void
    {
        $article = (int) $this->createArticle($this->createUser('author@example.test'))->getId();
        $alice = $this->createUser('alice@example.test');
        $this->actingAs($alice);
        $root = $this->postJson("/api/articles/{$article}/comments", ['body' => 'Корень'])->json('id');
        $this->actingAs($this->createUser('bob@example.test'));
        $reply = $this->postJson("/api/articles/{$article}/comments", ['body' => 'Ответ', 'reply_to' => $root])
            ->json('id');
        $this->putJson("/api/comments/{$root}", ['body' => 'Взлом'])->assertForbidden();
        $this->deleteJson("/api/comments/{$root}")->assertForbidden();

        $this->actingAs($alice);
        $this->putJson("/api/comments/{$root}", ['body' => 'Исправлено'])->assertOk()->assertJsonPath('edited', true);
        $this->deleteJson("/api/comments/{$root}")->assertOk();
        $thread = $this->getJson("/api/articles/{$article}/comments")->json('items.0');
        self::assertSame(
            ['deleted', null, null],
            [$thread['state'], $thread['body'], $thread['author']],
            'Удалённый корень с ответами остаётся заглушкой.',
        );

        $this->actingAsRole(Role::MODERATOR);
        $this->deleteJson("/api/comments/{$reply}")->assertOk();
        $this->getJson("/api/articles/{$article}/comments")->assertJsonPath('total', 0);
    }

    /**
     * Сортировка «популярные» идёт по реакциям; автор не реагирует на свою статью и свой комментарий.
     */
    public function test_popular_sort_uses_reactions_and_authors_cannot_react_to_themselves(): void
    {
        $author = $this->createUser('author@example.test');
        $article = (int) $this->createArticle($author)->getId();
        $alice = $this->createUser('alice@example.test');
        $this->actingAs($alice);
        $first = $this->postJson("/api/articles/{$article}/comments", ['body' => 'Первый'])->json('id');
        $second = $this->postJson("/api/articles/{$article}/comments", ['body' => 'Второй'])->json('id');
        $this->putJson("/api/comments/{$first}/reactions/like")->assertForbidden();

        $this->actingAs($author);
        $this->putJson("/api/articles/{$article}/reactions/like")->assertForbidden();
        $this->putJson("/api/comments/{$first}/reactions/like")->assertOk()
            ->assertJsonPath('counts.like', 1)
            ->assertJsonPath('mine', ['like']);
        $this->putJson("/api/comments/{$first}/reactions/love")->assertOk()->assertJsonPath('mine', ['like', 'love']);
        $this->putJson("/api/comments/{$first}/reactions/like")->assertOk()->assertJsonPath('counts.like', 1);
        $this->putJson("/api/comments/{$second}/reactions/angry")->assertOk();
        $this->putJson("/api/comments/{$first}/reactions/wow")->assertNotFound();

        self::assertSame(
            [$first, $second],
            array_column($this->getJson("/api/articles/{$article}/comments?sort=top")->json('items'), 'id'),
        );
        self::assertSame(
            [$second, $first],
            array_column($this->getJson("/api/articles/{$article}/comments?sort=new")->json('items'), 'id'),
        );
        $this->deleteJson("/api/comments/{$first}/reactions/love")->assertOk()->assertJsonPath('mine', ['like']);

        $this->actingAs($alice);
        $this->putJson("/api/articles/{$article}/reactions/insightful")->assertOk()
            ->assertJsonPath('counts.insightful', 1);
        $this->getJson("/api/articles/{$article}")
            ->assertJsonPath('reactions.mine', ['insightful'])
            ->assertJsonPath('permissions.react', true);
    }

    /**
     * Рейтинг спорных статей виден только администратору; модератор получает 403.
     */
    public function test_conflict_rating_is_for_administrators_only(): void
    {
        $article = (int) $this->createArticle($this->createUser('writer1@example.test'), 'Спорная')->getId();
        foreach (['like', 'dislike', 'love', 'angry'] as $i => $type) {
            $this->actingAs($this->createUser("voter{$i}@example.test"));
            $this->putJson("/api/articles/{$article}/reactions/{$type}")->assertOk();
        }
        $calm = (int) $this->createArticle($this->createUser('writer2@example.test'), 'Спокойная')->getId();
        $this->putJson("/api/articles/{$calm}/reactions/like")->assertOk();

        $this->actingAsRole(Role::ADMINISTRATOR, 'admin@example.test');
        $this->get('/admin/page/conflict-rating')->assertOk()->assertSee('Спорная')->assertDontSee('Спокойная');
        $this->flushSession();
        $this->actingAsRole(Role::MODERATOR);
        $this->get('/admin/page/conflict-rating')->assertForbidden();
    }

    /**
     * Ответ на несуществующий комментарий возвращает ошибку поля reply_to.
     */
    public function test_reply_to_missing_comment_is_a_field_error(): void
    {
        $article = (int) $this->createArticle($this->createUser('author@example.test'))->getId();
        $this->actingAs($this->createUser('replier@example.test'));

        $this->postJson("/api/articles/{$article}/comments", ['body' => 'Ответ', 'reply_to' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reply_to');
    }

    /**
     * Ответы загружаются с аватарами автора и адресата одним запросом: аватар адресата приходит загруженным.
     * Ответ адресата скрыт, поэтому его аватар попадает в выборку только через связь адресата.
     */
    public function test_replies_load_addressee_avatar_with_the_query(): void
    {
        $article = $this->createArticle($this->createUser('author@example.test'));
        $addressee = $this->createUser('addressee@example.test');
        $addressee->changeAvatar($this->avatarImage($addressee));
        $root = Comment::post($article, $this->createUser('root@example.test'), 'Корень');
        $answer = Comment::post($article, $addressee, 'Ответ', $root);
        $reply = Comment::post($article, $this->createUser('replier@example.test'), 'Ответ на ответ', $answer);
        $answer->hide();
        array_map($this->em->persist(...), [$root, $answer, $reply]);
        $this->em->flush();
        $this->em->clear();

        $replies = $this->app->make(CommentRepository::class)
            ->replies($this->em->find(Comment::class, $root->getId()), page: 1, limit: 10)
            ->items;
        $avatar = $replies->last()->getReplyTo()->getAvatar();

        self::assertNotNull($avatar);
        self::assertFalse($this->em->getUnitOfWork()->isUninitializedObject($avatar));
    }

    /**
     * Запись аватара пользователя без файлов: для проверки загрузки связей.
     */
    private function avatarImage(User $owner): Image
    {
        $image = Image::create(
            uuid: (string) Str::uuid(),
            purpose: ImagePurpose::Avatar,
            disk: StorageDriver::Local,
            directory: 'avatars/test',
            widths: collect([128]),
            dimensions: new ImageDimensions(128, 128),
            owner: $owner,
        );
        $this->em->persist($image);

        return $image;
    }
}
