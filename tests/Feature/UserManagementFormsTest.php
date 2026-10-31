<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Role;
use App\Entities\User;
use App\Repositories\UserPermissionRepository;
use Dom\NodeList;
use Tests\DoctrineTestCase;

/**
 * Пользователи в админке: таблица и её действия, правка аккаунта, роли в таблице и страница ролей.
 */
final class UserManagementFormsTest extends DoctrineTestCase
{
    /**
     * Владелец открывает список пользователей с их адресами.
     */
    public function test_admin_can_open_users_page(): void
    {
        $user = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $this->actingAs($user)->get('/admin/page/users')->assertOk()->assertSee('owner@example.test');
    }

    /**
     * Владелец открывает форму администратора в админке и сохраняет новые имя и email.
     */
    public function test_owner_can_edit_administrator_from_moonshine(): void
    {
        $owner = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $admin = $this->createUser('admin@example.test', 'Admin', Role::ADMINISTRATOR);
        $id = $admin->getAuthIdentifier();
        $this->actingAs($owner)->get('/admin/page/user-form?id=' . $id)->assertOk()
            ->assertSee('Редактировать пользователя')
            ->assertSee('admin@example.test');
        $this->put('/admin/content/users/' . $id, [
            'first_name' => 'Updated admin', 'email' => 'new-admin@example.test',
        ])->assertRedirect('/admin/page/users');
        $this->em->clear();
        $saved = $this->em->find(User::class, $id);
        self::assertSame('Updated admin', $saved->getName());
        self::assertSame('new-admin@example.test', $saved->getEmail());
    }

    /**
     * В таблице действия доступны только для тех пользователей, которыми владелец может управлять.
     */
    public function test_users_table_shows_actions_only_for_manageable_users(): void
    {
        $owner = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $this->createUser('target@example.test', 'Target');
        $this->actingAs($owner)->get('/admin/page/users')->assertOk()
            ->assertSee('target@example.test')
            ->assertSee('Редактировать пользователя Target')
            ->assertSee('Изменить роли')
            ->assertDontSee('Редактировать пользователя Owner');
    }

    /**
     * Роль назначается и снимается переключателем в таблице пользователей.
     */
    public function test_user_roles_can_be_assigned_and_revoked_in_table(): void
    {
        $owner = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $target = $this->createUser('target@example.test', 'Target');
        $id = $target->getAuthIdentifier();
        $response = $this->actingAs($owner)->get('/admin/page/users');
        $response->assertOk()->assertSee('Изменить роли')->assertSee('Модератор — Target');
        $checkboxes = $this->roleCheckboxes($response->getContent(), 'Модератор — Target');
        self::assertSame(1, $checkboxes->length);
        self::assertFalse($checkboxes->item(0)->hasAttribute('checked'));

        $this->post('/admin/content/users/' . $id . '/roles', ['role' => Role::MODERATOR, 'operation' => 'grant'])
            ->assertRedirect();
        self::assertContains(
            Role::MODERATOR,
            $this->app->make(UserPermissionRepository::class)->roles($target),
        );
        $this->post('/admin/content/users/' . $id . '/roles', ['role' => Role::MODERATOR, 'operation' => 'revoke'])
            ->assertRedirect();
        self::assertNotContains(
            Role::MODERATOR,
            $this->app->make(UserPermissionRepository::class)->roles($target),
        );
    }

    /**
     * Администратор не редактирует владельца и себя, модератор не редактирует пользователей вообще.
     */
    public function test_admin_cannot_edit_owner_or_self_and_moderator_cannot_edit_users(): void
    {
        $owner = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $admin = $this->createUser('admin@example.test', 'Admin', Role::ADMINISTRATOR);
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $data = ['first_name' => 'Changed', 'email' => 'changed@example.test'];

        $this->actingAs($admin)->put('/admin/content/users/' . $owner->getAuthIdentifier(), $data)->assertForbidden();
        $this->put('/admin/content/users/' . $admin->getAuthIdentifier(), $data)->assertForbidden();
        $this->flushSession();
        $this->actingAs($moderator)
            ->put('/admin/content/users/' . $admin->getAuthIdentifier(), $data)
            ->assertForbidden();
    }

    /**
     * Назначенная роль отмечена в таблице пользователей.
     */
    public function test_assigned_user_role_is_checked_in_table(): void
    {
        $owner = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $this->createUser('target@example.test', 'Target', Role::MODERATOR);
        $response = $this->actingAs($owner)->get('/admin/page/users')->assertOk();

        $checkboxes = $this->roleCheckboxes($response->getContent(), 'Модератор — Target');
        self::assertSame(1, $checkboxes->length);
        self::assertTrue($checkboxes->item(0)->hasAttribute('checked'));
    }

    /**
     * Правка пользователя не принимает email, который уже занят другим аккаунтом.
     */
    public function test_admin_user_edit_rejects_duplicate_email(): void
    {
        $owner = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $target = $this->createUser('target@example.test', 'Target');
        $this->actingAs($owner)->put('/admin/content/users/' . $target->getAuthIdentifier(), [
            'first_name' => 'Target', 'email' => 'owner@example.test',
        ])->assertSessionHasErrors('email');
    }

    /**
     * Владелец открывает страницу ролей и прав.
     */
    public function test_admin_can_open_roles_page(): void
    {
        $user = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $this->actingAs($user)->get('/admin/page/roles')->assertOk()->assertSee('Контент-менеджер');
    }

    /**
     * Чекбоксы роли в таблице пользователей с заданной подписью.
     */
    private function roleCheckboxes(string $html, string $label): NodeList
    {
        return $this->htmlNodes($html, 'input[aria-label="' . $label . '"]');
    }
}
