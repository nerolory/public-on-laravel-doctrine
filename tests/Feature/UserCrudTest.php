<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\User\UserData;
use App\Entities\User;
use App\Repositories\UserRepository;
use Tests\DoctrineTestCase;

/**
 * Репозиторий пользователей: сохранение, поиск по идентификатору и email, изменение и мягкое удаление.
 */
final class UserCrudTest extends DoctrineTestCase
{
    /** Репозиторий пользователей, через который проверяются все операции. */
    private UserRepository $users;

    /**
     * Сохранённого пользователя можно найти по идентификатору с теми же данными.
     */
    public function test_user_can_be_created(): void
    {
        $user = $this->newUser();
        $this->users->save($user);
        $id = $user->getAuthIdentifier();
        $this->em->clear();

        $saved = $this->users->find($id);
        self::assertInstanceOf(User::class, $saved);
        self::assertSame('Alice', $saved->getName());
        self::assertSame('alice@example.test', $saved->getEmail());
    }

    /**
     * Пользователя находят по email.
     */
    public function test_user_can_be_retrieved_by_email(): void
    {
        $user = $this->newUser();
        $this->users->save($user);
        $id = $user->getAuthIdentifier();
        $this->em->clear();

        $found = $this->users->findByEmail('alice@example.test');
        self::assertInstanceOf(User::class, $found);
        self::assertSame($id, $found->getAuthIdentifier());
    }

    /**
     * Изменённые имя и email сохраняются.
     */
    public function test_user_can_be_updated(): void
    {
        $user = $this->newUser();
        $this->users->save($user);
        $id = $user->getAuthIdentifier();
        $this->em->clear();

        $user = $this->users->find($id);
        $user->update(new UserData('Alice Updated', 'updated@example.test'));
        $this->users->save($user);
        $this->em->clear();

        $updated = $this->users->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertSame('Alice Updated', $updated->getName());
        self::assertSame('updated@example.test', $updated->getEmail());
    }

    /**
     * Удаление мягкое: пользователь пропадает из поиска, но email остаётся занятым.
     */
    public function test_user_can_be_deleted(): void
    {
        $user = $this->newUser();
        $this->users->save($user);
        $id = $user->getAuthIdentifier();
        $this->em->clear();

        $user = $this->users->find($id);
        $user->delete();
        $this->users->save($user);
        $this->em->clear();

        self::assertNull($this->users->find($id));
        self::assertNull($this->users->findByEmail('alice@example.test'));
        self::assertTrue($this->users->emailInUse('alice@example.test'));
        self::assertIsString($this->em->getConnection()->fetchOne('SELECT deleted_at FROM users WHERE id = ?', [$id]));
    }

    /**
     * Фильтр активных пользователей включается перед каждым тестом, репозиторий берётся из контейнера.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->em->getFilters()->enable('active_users');
        $this->users = $this->app->make(UserRepository::class);
    }

    /**
     * Новый пользователь для проверок репозитория.
     */
    private function newUser(): User
    {
        return User::create(new UserData('Alice', 'alice@example.test'), 'test-password-hash');
    }
}
