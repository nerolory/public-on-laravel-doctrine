<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\Admin\TopAuthorData;
use App\Entities\Role;
use App\Services\Admin\AdminMetricsService;
use Database\Seeders\LegacyPermissionSeeder;
use Doctrine\DBAL\Schema\Table;
use Tests\DoctrineTestCase;

/**
 * Доступ к админке: право входа по роли, назначенная роль, выход из админки, метрики и иерархия ролей.
 */
final class AdminAccessFormsTest extends DoctrineTestCase
{
    /**
     * Без роли админка и её действия закрыты, роль пользователя админку не открывает.
     */
    public function test_admin_is_denied_without_explicit_access(): void
    {
        $user = $this->createUser('alice@example.test', 'Alice');
        $this->actingAs($user);
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/content/articles', ['title' => 'Denied', 'content' => 'Denied'])->assertForbidden();
        $this->grantRole($user, Role::USER);
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/content/articles', ['title' => 'Denied', 'content' => 'Denied'])->assertForbidden();
    }

    /**
     * Администратор открывает панель, пока роль назначена; после снятия роли доступ закрывается.
     */
    public function test_designated_admin_can_open_dashboard(): void
    {
        $user = $this->createUser('alice@example.test', 'Alice');
        $this->actingAs($user);
        $access = $this->grantRole($user, Role::ADMINISTRATOR);
        $this->get('/admin')->assertOk();
        $this->em->remove($access);
        $this->em->flush();
        $this->get('/admin')->assertForbidden();
    }

    /**
     * Администратор выходит из админки через кнопку MoonShine и становится гостем.
     */
    public function test_admin_can_log_out_from_moonshine(): void
    {
        $user = $this->createUser('admin@example.test', 'Admin', Role::ADMINISTRATOR);
        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('Выйти')->assertSee('admin/logout');
        $this->delete('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    /**
     * Метрики считают статьи, пользователей и самых продуктивных авторов без заблокированных и удалённых.
     */
    public function test_dashboard_shows_counts_and_most_prolific_authors(): void
    {
        $owner = $this->createUser('owner@example.test', 'Owner', Role::OWNER);
        $author = $this->createUser('author@example.test', 'Author');
        $banned = $this->createUser('banned@example.test', 'Banned');
        $deleted = $this->createUser('deleted@example.test', 'Deleted');
        $banned->ban();
        $deleted->delete();
        $this->em->flush();
        $this->createArticle($author, 'Published', 'Text');
        $this->createArticle($author, 'Draft', 'Text', publish: false);
        $this->createArticle($owner, 'Other', 'Text', publish: false);

        $metrics = $this->app->make(AdminMetricsService::class)->get();
        self::assertSame(3, $metrics->articles->total);
        self::assertSame(3, $metrics->users->total);
        self::assertSame(2, $metrics->articles->unpublished);
        self::assertSame(1, $metrics->users->banned);
        self::assertEquals(
            [new TopAuthorData('Author', 2), new TopAuthorData('Owner', 1)],
            $metrics->topAuthors->all(),
        );
        $this->actingAs($owner)->get('/admin')->assertOk()->assertSee('Метрики')->assertSeeInOrder(['Author', 'Owner']);
    }

    /**
     * Иерархия ролей: контент-менеджер не входит в админку, модератор не банит, администратор не банит владельца.
     */
    public function test_role_hierarchy_and_admin_pages(): void
    {
        $users = [];
        foreach ([Role::OWNER, Role::ADMINISTRATOR, Role::MODERATOR, Role::CONTENT_MANAGER, Role::USER] as $role) {
            $users[$role] = $this->createUser($role . '@example.test', $role, $role);
        }
        $moderator = $users[Role::MODERATOR];
        $manager = $users[Role::CONTENT_MANAGER];
        $admin = $users[Role::ADMINISTRATOR];
        $owner = $users[Role::OWNER];
        $this->actingAs($manager)->get('/admin')->assertForbidden();
        $this->flushSession();
        $this->actingAs($moderator)->get('/admin')->assertOk();
        $this->actingAs($moderator)
            ->post('/admin/content/users/' . $manager->getAuthIdentifier() . '/ban')
            ->assertForbidden();
        $this->flushSession();
        $this->actingAs($admin)
            ->post('/admin/content/users/' . $owner->getAuthIdentifier() . '/ban')
            ->assertForbidden();
        $this->post('/admin/content/users/' . $manager->getAuthIdentifier() . '/roles', [
            'role' => Role::ADMINISTRATOR, 'operation' => 'grant',
        ])->assertForbidden();
        $this->flushSession();
        $this->actingAs($owner)->post('/admin/content/users/' . $admin->getAuthIdentifier() . '/roles', [
            'role' => Role::ADMINISTRATOR, 'operation' => 'revoke',
        ])->assertRedirect();
        $this->flushSession();
        $this->actingAs($admin)->get('/admin')->assertForbidden();
        $this->flushSession();
        $this->actingAs($owner)->get('/admin')->assertOk()->assertSee('Пользователи')->assertSee('Роли и права');
    }

    /**
     * Прежний флаг администратора переносится в роль администратора, обычные записи получают роль пользователя.
     */
    public function test_legacy_admin_permission_is_transferred_to_role(): void
    {
        $connection = $this->em->getConnection();
        $schema = $connection->createSchemaManager();
        $schema->dropTable('user_permissions');
        $table = new Table('user_permissions');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer');
        $table->addColumn('is_admin', 'boolean');
        $table->addColumn('role_id', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $schema->createTable($table);
        $connection->insert('user_permissions', ['user_id' => 1, 'is_admin' => 1]);
        $connection->insert('user_permissions', ['user_id' => 2, 'is_admin' => 0]);

        $this->runSeeder(LegacyPermissionSeeder::class);

        $rows = $connection->createQueryBuilder()->select('p.user_id', 'r.code')
            ->from('user_permissions', 'p')->innerJoin('p', 'roles', 'r', 'p.role_id = r.id')
            ->orderBy('p.user_id')->fetchAllAssociative();
        self::assertSame([Role::ADMINISTRATOR, Role::USER], array_column($rows, 'code'));
    }
}
