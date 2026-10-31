<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Image;
use App\Entities\Role;
use App\Entities\User;
use App\Enums\Settings\SettingGroup;
use Database\Seeders\ProfileNameSeeder;
use Illuminate\Http\UploadedFile;
use Tests\DoctrineTestCase;

/**
 * Профиль и аватар: данные аккаунта, видимость полей, сети, аватар, удаление аккаунта и карточка автора.
 */
final class ProfileTest extends DoctrineTestCase
{
    /**
     * Гость получает пустую сессию, вошедший пользователь — свою учётную запись и возможности.
     */
    public function test_session_is_null_for_guests_and_returns_account_for_users(): void
    {
        $this->getJson('/api/session')->assertOk()->assertExactJson(['user' => null]);
        $this->actingAs($this->createUser('ivan.petrov@example.test', 'Иван'));
        $this->getJson('/api/session')->assertOk()
            ->assertJsonPath('user.name', 'Иван')
            ->assertJsonPath('user.public_name', 'ivan.petrov')
            ->assertJsonPath('user.capabilities.delete_account', true)
            ->assertJsonPath('user.capabilities.admin', false);
    }

    /**
     * Скрытые поля профиля не попадают в публичную карточку автора.
     */
    public function test_profile_update_and_public_card_respect_visibility(): void
    {
        $user = $this->createUser('ivan@example.test', 'Иван');
        $this->actingAs($user);
        $profile = [
            'last_name' => 'Петров', 'first_name' => 'Иван', 'middle_name' => 'Сергеевич',
            'birth_date' => '1990-05-17', 'gender' => 'male', 'about' => 'Пишу о книгах.',
            'contacts' => ['phone' => '+7 900 000-00-00', 'email' => 'contact@example.test'],
            'socials' => ['vk' => 'https://vk.com/ivan', 'github' => 'https://github.com/ivan'],
            'visibility' => [
                'name' => true, 'about' => true, 'socials' => true,
                'birth_date' => false, 'gender' => false, 'contacts' => false,
            ],
        ];
        $this->putJson('/api/user/profile', $profile)->assertOk()
            ->assertJsonPath('user.name', 'Иван Сергеевич Петров')
            ->assertJsonPath('user.profile.birth_date', '1990-05-17')
            ->assertJsonPath('user.visibility.contacts', false);

        $card = $this->getJson('/api/authors/' . $user->getAuthIdentifier())->assertOk()->json();
        self::assertSame('Иван Сергеевич Петров', $card['name']);
        self::assertSame('Пишу о книгах.', $card['about']);
        self::assertNull($card['birth_date']);
        self::assertNull($card['gender']);
        self::assertSame([], $card['contacts']);
        self::assertSame(['vk' => 'https://vk.com/ivan', 'github' => 'https://github.com/ivan'], $card['socials']);

        $this->putJson('/api/user/profile', ['visibility' => ['name' => false]])->assertOk();
        $this->getJson('/api/authors/' . $user->getAuthIdentifier())
            ->assertJsonPath('name', 'ivan')
            ->assertJsonPath('about', null);
    }

    /**
     * Некорректные поля профиля возвращают ошибки; сеть появляется в профиле только после включения в настройках.
     */
    public function test_profile_is_validated_and_optional_networks_follow_settings(): void
    {
        $this->actingAs($this->createUser());
        $this->putJson('/api/user/profile', [
            'birth_date' => '2999-01-01', 'gender' => 'robot', 'socials' => ['vk' => 'http://vk.com/x'],
            'contacts' => ['phone' => 'call me'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date', 'gender', 'socials.vk', 'contacts.phone']);
        $this->putJson('/api/user/profile', ['socials' => ['facebook' => 'https://facebook.com/x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('socials');

        $this->updateSettings(SettingGroup::Profile, ['profile__network_facebook' => '1', 'profile__network_x' => '0']);
        $this->putJson('/api/user/profile', ['socials' => ['facebook' => 'https://facebook.com/x']])->assertOk();
        self::assertContains('facebook', $this->getJson('/api/user')->json('networks'));
    }

    /**
     * Аватар загружается, при замене старые файлы удаляются, при удалении аватара пропадает.
     */
    public function test_avatar_is_uploaded_replaced_and_removed(): void
    {
        $this->actingAs($this->createUser());
        $first = $this->post(
            '/api/user/avatar',
            ['file' => UploadedFile::fake()->image('a.jpg', 600, 400)],
            ['Accept' => 'application/json'],
        )
            ->assertOk()
            ->assertJsonPath('user.avatar.width', 128)
            ->json('user.avatar.id');
        $image = $this->em->getRepository(Image::class)->findOneBy(['uuid' => $first]);
        self::assertSame([64, 128, 256], $image->getWidths()->all());
        self::assertSame(
            400,
            (int) $this->em->getConnection()->fetchOne('SELECT width FROM images WHERE uuid = ?', [$first]),
            'Аватар обрезается до квадрата по меньшей стороне.',
        );
        $directory = $this->mediaRoot . '/' . $image->getDirectory();
        self::assertDirectoryExists($directory);

        $this->post(
            '/api/user/avatar',
            ['file' => UploadedFile::fake()->image('b.png', 300, 300)],
            ['Accept' => 'application/json'],
        )
            ->assertOk();
        self::assertDirectoryDoesNotExist($directory, 'Старый аватар удаляется вместе с файлами.');

        $this->deleteJson('/api/user/avatar')->assertOk()->assertJsonPath('user.avatar', null);
        self::assertSame(0, $this->em->getRepository(Image::class)->count([]));
    }

    /**
     * Владелец сайта не может удалить свой аккаунт, даже с правильным паролем.
     */
    public function test_owner_cannot_delete_own_account(): void
    {
        $owner = $this->actingAsRole(Role::OWNER, 'owner@example.test');
        $this->getJson('/api/user')->assertJsonPath('capabilities.delete_account', false);
        $this->deleteJson('/api/user', ['password' => self::PASSWORD])->assertForbidden();
        $this->em->clear();
        self::assertFalse($this->em->find(User::class, $owner->getAuthIdentifier())->isDeleted());
    }

    /**
     * Карточка заблокированного автора скрыта; у живого автора в карточке только опубликованные статьи.
     */
    public function test_banned_author_card_is_hidden_and_published_articles_are_listed(): void
    {
        $author = $this->createUser('author@example.test', 'Автор');
        $this->createArticle($author, 'Опубликованная', 'Текст');
        $this->createArticle($author, 'Черновик', 'Текст', publish: false);

        $this->getJson('/api/authors/' . $author->getAuthIdentifier())->assertOk()
            ->assertJsonCount(1, 'articles.items')
            ->assertJsonPath('articles.items.0.title', 'Опубликованная');
        $author->ban();
        $this->em->flush();
        $this->getJson('/api/authors/' . $author->getAuthIdentifier())->assertNotFound();
        $this->getJson('/api/authors/99999')->assertNotFound();
    }

    /**
     * Сидер переносит прежнее имя из users.name в «Имя» из ФИО.
     */
    public function test_name_seeder_moves_legacy_name_into_first_name(): void
    {
        $user = $this->createUser('legacy@example.test', '');
        $connection = $this->em->getConnection();
        $connection->executeStatement('ALTER TABLE users ADD COLUMN name VARCHAR(255) DEFAULT NULL');
        $connection->executeStatement(
            'UPDATE users SET name = ?, first_name = NULL WHERE id = ?',
            ['Старое имя', $user->getAuthIdentifier()],
        );

        $this->runSeeder(ProfileNameSeeder::class);
        self::assertSame(
            'Старое имя',
            $connection->fetchOne('SELECT first_name FROM users WHERE id = ?', [$user->getAuthIdentifier()]),
        );
    }
}
