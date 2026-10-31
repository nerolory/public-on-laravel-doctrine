<?php

declare(strict_types=1);

namespace Tests;

use App\DTO\Settings\SettingsGroupData;
use App\DTO\Settings\SettingValueData;
use App\DTO\User\UserData;
use App\Entities\Article;
use App\Entities\ArticleSection;
use App\Entities\Role;
use App\Entities\User;
use App\Entities\UserPermission;
use App\Enums\Settings\SettingGroup;
use App\Services\Settings\SettingsSchema;
use App\Services\Settings\SettingsService;
use App\Support\Media\MediaStorage;
use App\Support\Settings\SettingDefinition;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\LegalDocumentSeeder;
use Database\Seeders\SettingsSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Dom\HTMLDocument;
use Dom\NodeList;
use Illuminate\Database\Seeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/**
 * Feature-тесты на изолированной базе SQLite в памяти: схема строится по всем сущностям Doctrine,
 * роли, языки и настройки заполняются заранее. Сессии, кеш и ограничения частоты работают в памяти.
 */
abstract class DoctrineTestCase extends TestCase
{
    /** Пароль тестовых пользователей: используется при создании и при входе. */
    protected const string PASSWORD = 'password123';

    /** Текущий EntityManager приложения: тесты читают и пишут сущности через него. */
    protected EntityManagerInterface $em;

    /** Временная папка локального хранилища медиафайлов, удаляется после теста. */
    protected string $mediaRoot;

    /**
     * Удаляет временную папку медиафайлов.
     */
    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->mediaRoot);
        parent::tearDown();
    }

    /**
     * Подготавливает схему, роли, настройки и языки по умолчанию.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'testing';
        config([
            'database.connections.sqlite.database' => ':memory:',
            'doctrine.managers.default.connection' => 'sqlite',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'cache.limiter' => 'array',
            'mail.default' => 'array',
            'queue.default' => 'sync',
        ]);
        $this->mediaRoot = sys_get_temp_dir() . '/media-test-' . bin2hex(random_bytes(6));
        $this->app->scoped(MediaStorage::class, fn (): MediaStorage => new MediaStorage(
            $this->app->make(FilesystemManager::class),
            $this->app->make(SettingsService::class),
            $this->mediaRoot,
        ));
        $this->em = $this->app->make(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        foreach (Role::CODES as $code) {
            $this->em->persist(Role::create($code));
        }
        $this->em->flush();
        $this->runSeeder(SettingsSeeder::class);
        $this->runSeeder(LanguageSeeder::class);
    }

    /**
     * Запускает сидер так же, как его запускает artisan db:seed: зависимости внедряются контейнером.
     *
     * @param class-string<Seeder> $seeder
     */
    protected function runSeeder(string $seeder): void
    {
        $this->app->call([$this->app->make($seeder), 'run']);
    }

    /**
     * Публикует заглушки правовых документов по всем регионам. Нужна тестам, где проверяются согласия.
     */
    protected function seedLegalDocuments(): void
    {
        $this->runSeeder(LegalDocumentSeeder::class);
    }

    /**
     * Элементы HTML страницы по CSS-селектору.
     */
    protected function htmlNodes(string $html, string $selector): NodeList
    {
        return HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelectorAll($selector);
    }

    /**
     * Число названий из списка, которые есть в HTML страницы.
     *
     * @param list<string> $titles
     */
    protected function countTitlesIn(string $html, array $titles): int
    {
        return collect($titles)->filter(static fn (string $title): bool => str_contains($html, $title))->count();
    }

    /**
     * Сохраняет группу настроек по полям формы, как это делает админка. Поля, не переданные
     * в $changes, сохраняют текущее значение.
     *
     * @param array<string, mixed> $changes значения полей, которые меняются
     */
    protected function updateSettings(SettingGroup $group, array $changes): void
    {
        $input = $changes + $this->settingsFormValues($group);
        $values = $this->app->make(SettingsSchema::class)->group($group)->map(
            static fn (SettingDefinition $definition): SettingValueData => new SettingValueData(
                $definition->key->value,
                $input[$definition->field()],
            ),
        );

        $this->app->make(SettingsService::class)->update(new SettingsGroupData($group, $values->values()));
    }

    /**
     * Текущие значения группы в виде полей формы: булевы значения — «1» и «0».
     *
     * @return array<string, mixed>
     */
    protected function settingsFormValues(SettingGroup $group): array
    {
        $settings = $this->app->make(SettingsService::class);
        $values = [];

        foreach ($this->app->make(SettingsSchema::class)->group($group) as $definition) {
            $value = $settings->get($definition->key);
            $values[$definition->field()] = is_bool($value) ? ($value ? '1' : '0') : $value;
        }

        return $values;
    }

    /**
     * Создаёт пользователя с паролем по умолчанию и назначает роль, если она передана.
     */
    protected function createUser(
        string $email = 'user@example.test',
        string $name = 'Пользователь',
        ?string $role = null,
    ): User {
        $user = User::create(new UserData($name, $email), Hash::make(self::PASSWORD));
        $this->em->persist($user);
        $this->em->flush();
        if ($role !== null) {
            $this->grantRole($user, $role);
        }

        return $user;
    }

    /**
     * Назначает пользователю роль из справочника ролей.
     */
    protected function grantRole(User $user, string $role): UserPermission
    {
        $permission = UserPermission::create(
            $user,
            $this->em->getRepository(Role::class)->findOneBy(['code' => $role]),
        );
        $this->em->persist($permission);
        $this->em->flush();

        return $permission;
    }

    /**
     * Создаёт пользователя с ролью и входит под ним.
     */
    protected function actingAsRole(string $role, string $email = 'moderator@example.test'): User
    {
        $user = $this->createUser($email, 'Пользователь ' . $role, $role);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Создаёт статью автора. Без публикации статья остаётся на модерации.
     *
     * @param iterable<ArticleSection> $sections разделы статьи
     */
    protected function createArticle(
        User $author,
        string $title = 'Статья',
        string $content = '<p>Текст</p>',
        bool $publish = true,
        iterable $sections = [],
        ?string $summary = null,
    ): Article {
        $article = Article::create($title, $content, $author, $sections, $summary);
        if ($publish) {
            $article->publish();
        }
        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    /**
     * Идентификатор статьи по заголовку.
     */
    protected function articleIdByTitle(string $title): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM articles WHERE title = ?', [$title]);
    }

    /**
     * Текущая версия статьи: нужна для сохранения правок с защитой от конфликтов.
     */
    protected function articleVersion(int $id): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT version FROM articles WHERE id = ?', [$id]);
    }

    /**
     * Загружает картинку через API и возвращает её UUID.
     */
    protected function uploadImage(string $purpose, int $width = 1200, int $height = 800): string
    {
        return $this->postJson('/api/images', [
            'file' => UploadedFile::fake()->image('photo.jpg', $width, $height),
            'purpose' => $purpose,
        ])->assertCreated()->json('id');
    }

    /**
     * Создаёт альбом владельца через API и возвращает его UUID. Новый альбом ждёт модерации.
     */
    protected function createAlbum(User $owner, string $title = 'Отпуск'): string
    {
        $this->actingAs($owner);

        return $this->postJson('/api/albums', [
            'title' => $title,
            'items' => [['image' => $this->uploadImage('album'), 'alt' => 'Море']],
        ])->assertCreated()->json('id');
    }

    /**
     * Регистрирует пользователя через API; пароль подставляется по умолчанию.
     *
     * @param array<string, mixed>  $fields  имя, email и согласия
     * @param array<string, string> $headers заголовки запроса
     */
    protected function registerUser(array $fields, array $headers = []): TestResponse
    {
        return $this->postJson('/api/register', $fields + [
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $headers);
    }

    /**
     * Сбрасывает авторизацию в приложении, чтобы следующий запрос шёл как гость.
     */
    protected function asGuest(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
