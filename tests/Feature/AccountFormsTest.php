<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\User;
use Illuminate\Support\Facades\Hash;
use Tests\DoctrineTestCase;

/**
 * Аккаунт: регистрация, вход и выход, данные профиля, удаление аккаунта и ограничения для гостей и заблокированных.
 */
final class AccountFormsTest extends DoctrineTestCase
{
    /**
     * Регистрация создаёт пользователя с хешем пароля и сразу входит в аккаунт.
     */
    public function test_registration_creates_and_authenticates_user(): void
    {
        $this->registerUser(['name' => 'Alice', 'email' => 'alice@example.test'])->assertCreated();

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.test']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('Alice', $user->getName());
        self::assertTrue(Hash::check(self::PASSWORD, $user->getAuthPassword()));
        $this->assertAuthenticated();
    }

    /**
     * Полный цикл через JSON API: регистрация, правка профиля, выход, вход и удаление аккаунта.
     */
    public function test_json_account_flow(): void
    {
        $this->registerUser(['name' => 'Alice', 'email' => 'alice@example.test'])->assertCreated();
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('name', 'Alice')
            ->assertJsonPath('email', 'alice@example.test');
        $this->putJson('/api/user', ['name' => 'Alice Updated', 'email' => 'updated@example.test'])->assertOk();
        $this->getJson('/api/user')->assertJsonPath('name', 'Alice Updated');
        $this->em->clear();
        $updated = $this->em->getRepository(User::class)->findOneBy(['email' => 'updated@example.test']);
        self::assertInstanceOf(User::class, $updated);
        self::assertSame('Alice Updated', $updated->getName());

        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'updated@example.test', 'password' => self::PASSWORD])->assertOk();
        $this->deleteJson('/api/user', ['password' => self::PASSWORD])->assertOk();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    /**
     * Удаление аккаунта требует верного пароля; после удаления вход и регистрация с тем же email невозможны.
     */
    public function test_user_must_confirm_password_to_delete_account(): void
    {
        $user = $this->createUser('alice@example.test', 'Alice');
        $id = $user->getAuthIdentifier();
        $this->actingAs($user);

        $this->deleteJson('/api/user', ['password' => 'wrong-password'])->assertJsonValidationErrors('password');
        self::assertNotNull($this->em->find(User::class, $id));
        $this->deleteJson('/api/user', ['password' => self::PASSWORD])->assertOk();
        $this->em->clear();
        self::assertNull($this->em->find(User::class, $id));
        self::assertIsString($this->em->getConnection()->fetchOne('SELECT deleted_at FROM users WHERE id = ?', [$id]));
        $this->assertGuest();
        $this->postJson('/api/login', ['email' => 'alice@example.test', 'password' => self::PASSWORD])
            ->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->registerUser(['name' => 'Alice', 'email' => 'alice@example.test'])
            ->assertJsonValidationErrors('email');
    }

    /**
     * Заблокированный пользователь не входит по паролю.
     */
    public function test_banned_user_cannot_sign_in(): void
    {
        $user = $this->createUser('banned@example.test', 'Banned');
        $user->ban();
        $this->em->flush();

        $this->postJson('/api/login', ['email' => 'banned@example.test', 'password' => self::PASSWORD])
            ->assertUnprocessable();
        $this->assertGuest();
    }

    /**
     * Активная сессия заблокированного пользователя перестаёт работать.
     */
    public function test_active_session_of_banned_user_is_rejected(): void
    {
        $user = $this->createUser('banned@example.test', 'Banned');
        $user->ban();
        $this->em->flush();

        $this->actingAs($user)->getJson('/api/user')->assertForbidden();
        $this->assertGuest();
    }

    /**
     * Неверный пароль возвращает ошибку входа, верный пароль входит в аккаунт.
     */
    public function test_login_rejects_wrong_password_and_accepts_valid_credentials(): void
    {
        $this->createUser('alice@example.test', 'Alice');
        $this->em->clear();

        $this->postJson('/api/login', ['email' => 'alice@example.test', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->postJson('/api/login', ['email' => 'alice@example.test', 'password' => self::PASSWORD])->assertOk();
        $this->assertAuthenticated();
    }

    /**
     * Регистрация с занятым email отклоняется.
     */
    public function test_duplicate_email_is_rejected_at_registration(): void
    {
        $this->createUser('alice@example.test', 'Alice');

        $this->registerUser(['name' => 'Bob', 'email' => 'alice@example.test'])
            ->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    /**
     * Гость не читает, не меняет и не удаляет аккаунт.
     */
    public function test_guests_cannot_change_or_delete_accounts(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->putJson('/api/user', [])->assertUnauthorized();
        $this->deleteJson('/api/user', [])->assertUnauthorized();
        $this->get('/api/user')->assertRedirect('/login');
    }
}
