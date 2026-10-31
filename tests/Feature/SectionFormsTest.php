<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Article;
use App\Entities\ArticleSection;
use App\Entities\Role;
use App\Entities\User;
use App\Services\Article\ArticleSectionService;
use Database\Seeders\ArticleSectionLinkSeeder;
use Database\Seeders\ArticleSectionSeeder;
use Tests\DoctrineTestCase;

/**
 * Разделы статей: права на создание, дерево разделов любой глубины, правка и удаление, выбор разделов в статье,
 * фильтр по подразделам и сидеры дерева разделов.
 */
final class SectionFormsTest extends DoctrineTestCase
{
    /**
     * Создавать разделы могут владелец, администратор и модератор; остальные получают 403.
     * Повтор названия уже созданного раздела отклоняется ошибкой поля.
     */
    public function test_moderator_and_higher_can_create_sections_but_other_users_cannot(): void
    {
        $users = [];
        foreach ([Role::OWNER, Role::ADMINISTRATOR, Role::MODERATOR, Role::CONTENT_MANAGER, Role::USER] as $code) {
            $users[$code] = $this->createUser($code . '@example.test', $code, $code);
        }
        foreach ([Role::CONTENT_MANAGER, Role::USER] as $code) {
            $this->flushSession();
            $this->actingAs($users[$code])
                ->post('/admin/content/sections', ['name' => 'Denied ' . $code])
                ->assertForbidden();
        }
        foreach ([Role::OWNER, Role::ADMINISTRATOR, Role::MODERATOR] as $code) {
            $this->flushSession();
            $this->actingAs($users[$code])->post('/admin/content/sections', ['name' => 'Section ' . $code])
                ->assertRedirect('/admin/page/article-sections');
        }
        $this->post('/admin/content/sections', ['name' => 'Section ' . Role::MODERATOR])
            ->assertSessionHasErrors('name');
        $this->getJson('/api/sections')->assertOk()->assertJsonCount(3, 'items');
    }

    /**
     * Автор выбирает существующий раздел для черновика; после публикации раздел виден в статье и в ленте.
     */
    public function test_author_selects_existing_section_and_publication_displays_it(): void
    {
        $user = $this->createUser('author@example.test', 'Author');
        $section = ArticleSection::create('Технологии');
        $this->em->persist($section);
        $this->em->flush();
        $sectionId = $this->em->getConnection()
            ->fetchOne('SELECT id FROM article_sections WHERE name = ?', ['Технологии']);
        $this->actingAs($user)->postJson('/api/articles', [
            'title' => 'Draft', 'content' => 'Text', 'section_ids' => [$sectionId],
        ])->assertCreated();
        $this->postJson('/api/articles', ['title' => 'Invalid', 'content' => 'Text', 'section_ids' => [99999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('section_ids');
        $this->postJson('/api/articles', ['title' => 'Invalid', 'content' => 'Text', 'section_ids' => ['abc']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('section_ids.0');
        $articleId = $this->articleIdByTitle('Draft');
        $this->em->clear();
        $article = $this->em->find(Article::class, $articleId);
        $article->publish();
        $this->em->flush();
        $this->getJson('/api/articles/' . $articleId)->assertOk()->assertJsonPath('sections', ['Технологии']);
        $this->getJson('/api/articles')->assertJsonPath('items.0.sections', ['Технологии']);
    }

    /**
     * Модератор открывает список разделов с действиями.
     */
    public function test_moderator_can_open_section_pages(): void
    {
        $user = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $this->actingAs($user)->get('/admin/page/article-sections')->assertOk()->assertSee('Создать раздел');
    }

    /**
     * Модератор открывает форму нового раздела.
     */
    public function test_moderator_can_open_section_creation_form(): void
    {
        $user = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $this->actingAs($user)->get('/admin/page/article-section-form')->assertOk()
            ->assertSee('Новый раздел')
            ->assertSee('Название');
    }

    /**
     * Разделы образуют дерево любой глубины; ключи разделов генерируются и не принимаются извне.
     */
    public function test_sections_form_a_tree_of_any_depth(): void
    {
        $moderator = $this->createUser('moderator@example.test', 'Moderator', Role::MODERATOR);
        $this->actingAs($moderator);

        $this->post('/admin/content/sections', ['name' => 'Технологии'])->assertSessionHasNoErrors();
        $this->post('/admin/content/sections', ['name' => 'Аналитика'])->assertSessionHasNoErrors();
        $rootId = $this->sectionId('Технологии');
        $this->post('/admin/content/sections', ['name' => 'Разработка', 'parent_id' => $rootId])
            ->assertSessionHasNoErrors();
        $this->post('/admin/content/sections', ['name' => 'Безопасность', 'parent_id' => $rootId])
            ->assertSessionHasNoErrors();
        $this->post('/admin/content/sections', ['name' => 'Глубже', 'parent_id' => $this->sectionId('Разработка')])
            ->assertSessionHasNoErrors();
        $this->post('/admin/content/sections', ['name' => 'Ещё глубже', 'parent_id' => $this->sectionId('Глубже')])
            ->assertSessionHasNoErrors();
        $this->post('/admin/content/sections', ['name' => 'Без родителя', 'parent_id' => 99999])
            ->assertSessionHasErrors('parent_id');
        $this->post('/admin/content/sections', ['name' => 'Со своим ключом', 'slug' => 'svoy-klyuch'])
            ->assertSessionHasErrors('slug');

        $items = $this->getJson('/api/sections')->assertOk()->json('items');
        self::assertSame(
            ['Аналитика', 'Технологии', 'Безопасность', 'Разработка', 'Глубже', 'Ещё глубже'],
            array_column($items, 'name'),
        );
        self::assertSame([0, 0, 1, 1, 2, 3], array_column($items, 'level'));
        self::assertSame(
            [null, null, $items[1]['slug'], $items[1]['slug'], $items[3]['slug'], $items[4]['slug']],
            array_column($items, 'parent'),
        );
        foreach ($items as $item) {
            self::assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $item['slug']);
        }
        $this->get('/admin/page/article-section-form')->assertOk()->assertSee('Родительский раздел');
        $this->get('/admin/page/article-sections')->assertOk()
            ->assertSee('— Разработка')
            ->assertSee('Изменить раздел')
            ->assertSee('Удалить раздел');
    }

    /**
     * Модератор меняет название, ключ и родителя раздела; перенос раздела пересчитывает уровни вложенных.
     */
    public function test_moderator_edits_section_name_slug_and_parent(): void
    {
        $this->actingAsRole(Role::MODERATOR);
        $sections = $this->app->make(ArticleSectionService::class);
        $tech = $sections->create('Технологии');
        $dev = $sections->create('Разработка', $tech->getId());
        $sections->create('Базы данных', $dev->getId());
        $walks = $sections->create('Прогулки');
        $devSlug = $this->sectionSlug('Разработка');

        $this->get('/admin/page/article-section-form?id=' . $dev->getId())->assertOk()
            ->assertSee('Изменить раздел')
            ->assertSee('Ключ в адресе')
            ->assertSee($devSlug);
        $this->put('/admin/content/sections/' . $dev->getId(), [
            'name' => 'Код', 'slug' => $devSlug, 'parent_id' => $tech->getId(),
        ])->assertSessionHasNoErrors();
        self::assertSame($devSlug, $this->sectionSlug('Код'));

        $this->put('/admin/content/sections/' . $dev->getId(), [
            'name' => 'Код', 'slug' => 'kod', 'parent_id' => $walks->getId(),
        ])->assertSessionHasNoErrors();
        self::assertSame('kod', $this->sectionSlug('Код'));
        $moved = array_column($this->getJson('/api/sections')->json('items'), null, 'name');
        self::assertSame($this->sectionSlug('Прогулки'), $moved['Код']['parent']);
        self::assertSame(2, $moved['Базы данных']['level']);

        $this->put('/admin/content/sections/' . $dev->getId(), ['name' => 'Код', 'slug' => 'kod', 'parent_id' => null])
            ->assertSessionHasNoErrors();
        $items = array_column($this->getJson('/api/sections')->json('items'), null, 'name');
        self::assertNull($items['Код']['parent']);
        self::assertSame(0, $items['Код']['level']);
    }

    /**
     * Правка раздела отклоняет занятый или некорректный ключ, занятое название и перенос в своё поддерево.
     */
    public function test_section_edit_rejects_taken_slug_duplicate_name_and_cycles(): void
    {
        $this->actingAsRole(Role::MODERATOR);
        $sections = $this->app->make(ArticleSectionService::class);
        $tech = $sections->create('Технологии');
        $dev = $sections->create('Разработка', $tech->getId());
        $deep = $sections->create('Базы данных', $dev->getId());
        $sections->create('Прогулки');
        $devUrl = '/admin/content/sections/' . $dev->getId();

        $this->put($devUrl, ['name' => 'Код', 'slug' => $this->sectionSlug('Прогулки'), 'parent_id' => null])
            ->assertSessionHasErrors('slug');
        $this->put($devUrl, ['name' => 'Код', 'slug' => 'Плохой ключ', 'parent_id' => null])
            ->assertSessionHasErrors('slug');
        $this->put($devUrl, ['name' => 'Прогулки', 'slug' => 'kod', 'parent_id' => null])
            ->assertSessionHasErrors('name');
        $this->put($devUrl, ['name' => 'Код', 'slug' => 'kod', 'parent_id' => $deep->getId()])
            ->assertSessionHasErrors('parent_id');
        $this->put($devUrl, ['name' => 'Код', 'slug' => 'kod', 'parent_id' => $dev->getId()])
            ->assertSessionHasErrors('parent_id');
        $this->put('/admin/content/sections/99999', ['name' => 'Нет', 'slug' => 'net'])->assertNotFound();

        $row = $this->em->getConnection()
            ->fetchAssociative('SELECT name, parent_id FROM article_sections WHERE id = ?', [$dev->getId()]);
        self::assertSame('Разработка', $row['name'], 'Отклонённая правка не меняет название.');
        self::assertSame($tech->getId(), (int) $row['parent_id'], 'Отклонённая правка не меняет родителя.');
    }

    /**
     * При удалении раздела статьи и подразделы переходят к родителю; корневой раздел с подразделами не удаляется.
     */
    public function test_deleting_section_moves_articles_and_subsections_to_parent(): void
    {
        $author = $this->actingAsRole(Role::MODERATOR);
        $sections = $this->app->make(ArticleSectionService::class);
        $root = $sections->create('Технологии');
        $child = $sections->create('Разработка', $root->getId());
        $grandchild = $sections->create('Базы данных', $child->getId());
        $empty = $sections->create('Пустой');
        $this->createArticle($author, 'Только в подразделе', 'Текст', publish: false, sections: [$child]);
        $this->createArticle($author, 'В подразделе и в корне', 'Текст', publish: false, sections: [$child, $root]);
        $this->createArticle($author, 'Во вложенном', 'Текст', publish: false, sections: [$grandchild]);
        $this->em->clear();

        $this->delete('/admin/content/sections/' . $root->getId())->assertSessionHasErrors('section');
        $this->delete('/admin/content/sections/' . $child->getId())->assertRedirect('/admin/page/article-sections');
        $this->assertArticlesMovedToParent();

        $this->delete('/admin/content/sections/' . $empty->getId())->assertRedirect('/admin/page/article-sections');
        self::assertSame(
            0,
            (int) $this->em->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM article_sections WHERE name = ?',
                ['Пустой'],
            ),
        );
        $page = $this->get('/admin/page/article-sections')->assertOk()->getContent();
        self::assertTrue($this->deleteButtonDisabled($page, 'Технологии'), 'Корень с подразделом не удаляется.');
        self::assertFalse($this->deleteButtonDisabled($page, 'Базы данных'), 'Раздел без подразделов удаляется.');
        $this->delete('/admin/content/sections/99999')->assertNotFound();

        $this->flushSession();
        $this->actingAsRole(Role::CONTENT_MANAGER, 'manager@example.test');
        $this->delete('/admin/content/sections/' . $grandchild->getId())->assertForbidden();
        $this->put('/admin/content/sections/' . $grandchild->getId(), ['name' => 'X', 'slug' => 'x'])
            ->assertForbidden();
    }

    /**
     * Редактор статьи выбирает несколько разделов; снятие всех разделов очищает связи.
     */
    public function test_admin_article_editor_selects_several_sections(): void
    {
        $this->actingAsRole(Role::MODERATOR);
        $sections = $this->app->make(ArticleSectionService::class);
        $root = $sections->create('Технологии');
        $child = $sections->create('Разработка', $root->getId());
        $other = $sections->create('Прогулки');

        $this->get('/admin/page/article-form')->assertOk()
            ->assertSee('/build/admin/assets/article-editor-', escape: false)
            ->assertSee('Разделы')
            ->assertSee('multiple', escape: false)
            ->assertSee('— Разработка');
        $this->post('/admin/content/articles', [
            'title' => 'Две темы', 'content' => 'Текст',
            'section_ids' => [$child->getId(), $other->getId(), $root->getId()],
        ])->assertSessionHasNoErrors();
        self::assertSame(['Прогулки', 'Разработка', 'Технологии'], $this->articleSectionNames('Две темы'));

        $articleId = $this->articleIdByTitle('Две темы');
        $form = $this->get('/admin/page/article-form?id=' . $articleId)->assertOk()->getContent();
        foreach ([$child, $other, $root] as $section) {
            self::assertMatchesRegularExpression('/<option\s+selected[^>]*value="' . $section->getId() . '"/', $form);
        }
        $this->put('/admin/content/articles/' . $articleId, [
            'title' => 'Две темы', 'content' => 'Текст', 'section_ids' => [$other->getId()],
            'version' => $this->articleVersion($articleId),
        ])->assertSessionHasNoErrors();
        self::assertSame(['Прогулки'], $this->articleSectionNames('Две темы'));
        $this->put('/admin/content/articles/' . $articleId, [
            'title' => 'Две темы', 'content' => 'Текст', 'version' => $this->articleVersion($articleId),
        ])->assertSessionHasNoErrors();
        self::assertSame([], $this->articleSectionNames('Две темы'));
    }

    /**
     * Сидер связей переносит прежний раздел статьи в таблицу связей; повторный запуск ничего не дублирует.
     */
    public function test_link_seeder_copies_legacy_section_column(): void
    {
        $author = $this->createUser('author@example.test', 'Author');
        $section = ArticleSection::create('Технологии');
        $this->em->persist($section);
        $this->em->flush();
        $this->createArticle($author, 'Старая статья', 'Текст', publish: false);
        $connection = $this->em->getConnection();

        $this->runSeeder(ArticleSectionLinkSeeder::class);
        self::assertSame([], $this->articleSectionNames('Старая статья'));

        $connection->executeStatement('ALTER TABLE articles ADD COLUMN section_id INTEGER DEFAULT NULL');
        $connection->executeStatement(
            'UPDATE articles SET section_id = ? WHERE title = ?',
            [$section->getId(), 'Старая статья'],
        );
        $this->runSeeder(ArticleSectionLinkSeeder::class);
        $this->runSeeder(ArticleSectionLinkSeeder::class);
        self::assertSame(['Технологии'], $this->articleSectionNames('Старая статья'));
    }

    /**
     * Фильтр по разделу включает статьи его подразделов; черновики и неизвестные разделы не попадают в выдачу.
     */
    public function test_article_filter_includes_subsections(): void
    {
        $sections = $this->app->make(ArticleSectionService::class);
        $root = $sections->create('Технологии');
        $child = $sections->create('Разработка', $root->getId());
        $other = $sections->create('Прогулки');
        $grandchild = $sections->create('Базы данных', $child->getId());
        $author = $this->createUser('author@example.test', 'Author');
        $this->createFilterArticles($author, $root, $child, $other, $grandchild);

        $this->getJson('/api/articles')->assertOk()->assertJsonCount(6, 'items');
        self::assertEqualsCanonicalizing(
            ['В корне', 'В подразделе', 'Глубоко', 'В двух ветках'],
            $this->filteredTitles('Технологии'),
        );
        self::assertEqualsCanonicalizing(
            ['В подразделе', 'Глубоко', 'В двух ветках'],
            $this->filteredTitles('Разработка'),
        );
        self::assertEqualsCanonicalizing(
            ['В двух ветках', 'В другом разделе'],
            $this->filteredTitles('Прогулки'),
        );
        $twoBranches = collect($this->getJson('/api/articles')->json('items'))->firstWhere('title', 'В двух ветках');
        self::assertSame(['Прогулки', 'Разработка', 'Технологии'], $twoBranches['sections']);
        $this->getJson('/api/articles?section=net-takogo-razdela')->assertNotFound();
        $this->getJson('/api/articles?section=Bad%20slug')->assertUnprocessable()
            ->assertJsonValidationErrors('section');
    }

    /**
     * Сидер дерева создаёт разделы по умолчанию, раскладывает статьи и не трогает выбранные пользователем разделы.
     */
    public function test_section_seeder_builds_tree_and_keeps_chosen_sections(): void
    {
        $author = $this->createUser('author@example.test', 'Author');
        $custom = ArticleSection::create('Мой раздел');
        $this->em->persist($custom);
        $this->em->flush();
        $this->createArticle($author, 'Резервные копии', 'Текст', publish: false);
        $this->createArticle($author, 'Читать медленно', 'Текст', publish: false, sections: [$custom]);
        $this->createArticle($author, 'Неизвестная статья', 'Текст', publish: false);

        $this->runSeeder(ArticleSectionSeeder::class);
        $this->runSeeder(ArticleSectionSeeder::class);

        $connection = $this->em->getConnection();
        self::assertSame(10, (int) $connection->fetchOne('SELECT COUNT(*) FROM article_sections'));
        self::assertSame(5, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM article_sections WHERE parent_id IS NULL AND name <> ?',
            ['Мой раздел'],
        ));
        self::assertSame(['Цифровая гигиена'], $this->articleSectionNames('Резервные копии'));
        self::assertSame(['Мой раздел'], $this->articleSectionNames('Читать медленно'));
        self::assertSame([], $this->articleSectionNames('Неизвестная статья'));
        self::assertSame('Технологии', $connection->fetchOne(
            'SELECT p.name FROM article_sections s JOIN article_sections p ON p.id = s.parent_id WHERE s.name = ?',
            ['Цифровая гигиена'],
        ));

        $titles = [];
        foreach (ArticleSectionSeeder::TREE as $root) {
            $titles = [...$titles, ...$root['articles']];
            foreach ($root['children'] as $childTitles) {
                $titles = [...$titles, ...$childTitles];
            }
        }
        self::assertCount(120, array_unique($titles));
    }

    /**
     * Статьи для проверки фильтра: раздел выбирает статью вместе с его подразделами.
     */
    private function createFilterArticles(
        User $author,
        ArticleSection $root,
        ArticleSection $child,
        ArticleSection $other,
        ArticleSection $grandchild,
    ): void {
        foreach ([
            'В корне' => [$root],
            'В подразделе' => [$child],
            'Глубоко' => [$grandchild],
            'В двух ветках' => [$child, $other, $root],
            'В другом разделе' => [$other],
            'Без раздела' => [],
        ] as $title => $articleSections) {
            $this->createArticle($author, $title, 'Текст', sections: $articleSections);
        }
        $this->createArticle($author, 'Черновик в подразделе', 'Текст', publish: false, sections: [$child]);
    }

    /**
     * Названия статей, которые выводит лента по разделу с указанным названием.
     *
     * @return list<string>
     */
    private function filteredTitles(string $sectionName): array
    {
        $response = $this->getJson('/api/articles?section=' . $this->sectionSlug($sectionName))->assertOk();

        return array_column($response->json('items'), 'title');
    }

    /**
     * После удаления раздела статьи и подразделы указывают на родителя, удалённый раздел исчезает из дерева.
     */
    private function assertArticlesMovedToParent(): void
    {
        self::assertSame(['Технологии'], $this->articleSectionNames('Только в подразделе'));
        self::assertSame(['Технологии'], $this->articleSectionNames('В подразделе и в корне'));
        self::assertSame(['Базы данных'], $this->articleSectionNames('Во вложенном'));
        $items = array_column($this->getJson('/api/sections')->json('items'), null, 'name');
        self::assertArrayNotHasKey('Разработка', $items);
        self::assertSame($this->sectionSlug('Технологии'), $items['Базы данных']['parent']);
        self::assertSame(1, $items['Базы данных']['level']);
    }

    /**
     * Отключена ли кнопка удаления раздела на странице разделов.
     */
    private function deleteButtonDisabled(string $page, string $name): bool
    {
        $buttons = $this->htmlNodes($page, 'button[aria-label="Удалить раздел ' . $name . '"]');
        self::assertSame(1, $buttons->length, 'Кнопка удаления раздела «' . $name . '» есть на странице один раз.');

        return $buttons->item(0)->hasAttribute('disabled');
    }

    /**
     * Идентификатор раздела по названию.
     */
    private function sectionId(string $name): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM article_sections WHERE name = ?', [$name]);
    }

    /**
     * Ключ раздела в адресе по названию.
     */
    private function sectionSlug(string $name): string
    {
        return (string) $this->em->getConnection()
            ->fetchOne('SELECT slug FROM article_sections WHERE name = ?', [$name]);
    }

    /**
     * Названия разделов статьи по её заголовку.
     *
     * @return list<string>
     */
    private function articleSectionNames(string $title): array
    {
        return $this->em->getConnection()->fetchFirstColumn(
            'SELECT s.name FROM article_section_links l JOIN articles a ON a.id = l.article_id '
            . 'JOIN article_sections s ON s.id = l.section_id WHERE a.title = ? ORDER BY s.name',
            [$title],
        );
    }
}
