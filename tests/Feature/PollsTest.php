<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Article;
use App\Entities\User;
use App\Enums\Settings\SettingGroup;
use Tests\DoctrineTestCase;

/**
 * Опросы в статьях: настройки и проверка вопроса, голосование, закрытие, список голосовавших и правки автора.
 */
final class PollsTest extends DoctrineTestCase
{
    /**
     * Число вариантов, уникальность, лимит выбора и срок опроса проверяются при создании статьи.
     */
    public function test_poll_settings_are_validated(): void
    {
        $this->actingAs($this->createUser('author@example.test'));
        $this->updateSettings(SettingGroup::Content, ['content__poll_max_options' => 3]);
        $base = ['title' => 'Опрос', 'content' => '<p>Текст</p>'];

        $this->postJson('/api/articles', $base + ['poll' => ['question' => 'Как?', 'options' => ['Один']]])
            ->assertJsonValidationErrors('poll.options');
        $this->postJson('/api/articles', $base + ['poll' => ['question' => 'Как?', 'options' => ['1', '2', '3', '4']]])
            ->assertJsonValidationErrors('poll.options');
        $this->postJson('/api/articles', $base + ['poll' => ['question' => 'Как?', 'options' => ['Да', 'да']]])
            ->assertJsonValidationErrors('poll.options.1');
        $this->postJson('/api/articles', $base + ['poll' => [
            'question' => 'Как?', 'options' => ['А', 'Б'], 'multiple' => true, 'max_choices' => 3,
        ]])->assertJsonValidationErrors('poll.max_choices');
        $this->postJson('/api/articles', $base + ['poll' => [
            'question' => 'Как?', 'options' => ['А', 'Б'], 'ends_at' => '2000-01-01T10:00',
        ]])->assertJsonValidationErrors('poll.ends_at');
        $this->postJson('/api/articles', $base + ['poll' => [
            'question' => 'Как?', 'options' => ['А', 'Б'],
            'multiple' => false, 'allow_revote' => false, 'anonymous' => true,
        ]])->assertCreated();
    }

    /**
     * Гость не голосует; один голос без переголосования фиксирует выбор и блокирует повтор.
     */
    public function test_single_choice_vote_without_revote(): void
    {
        $poll = $this->publishedPoll(['options' => ['Да', 'Нет']]);
        $this->asGuest();
        $this->getJson('/api/articles/' . $poll['article'])->assertOk()
            ->assertJsonPath('poll.can_vote', false)
            ->assertJsonPath('poll.total_voters', 0)
            ->assertJsonPath('poll.my_choice', null);
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$poll['options'][0]]])
            ->assertUnauthorized();

        $this->actingAs($this->createUser('voter@example.test'));
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => $poll['options']])
            ->assertJsonValidationErrors('options');
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [999999]])
            ->assertJsonValidationErrors('options');
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$poll['options'][0]]])->assertOk()
            ->assertJsonPath('total_voters', 1)
            ->assertJsonPath('options.0.votes', 1)
            ->assertJsonPath('options.0.percent', 100)
            ->assertJsonPath('my_choice', [$poll['options'][0]])
            ->assertJsonPath('can_vote', false);
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$poll['options'][1]]])
            ->assertJsonValidationErrors('options');
        $this->deleteJson('/api/polls/' . $poll['id'] . '/votes')->assertJsonValidationErrors('options');
    }

    /**
     * Выбор нескольких вариантов ограничен лимитом; переголосование заменяет прежний выбор, отмена снимает его.
     */
    public function test_multiple_choice_with_limit_and_revote(): void
    {
        $poll = $this->publishedPoll([
            'options' => ['А', 'Б', 'В'], 'multiple' => true, 'max_choices' => 2, 'allow_revote' => true,
        ]);
        $this->actingAs($this->createUser('voter@example.test'));
        [$a, $b, $c] = $poll['options'];

        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$a, $b, $c]])
            ->assertJsonValidationErrors('options');
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$a, $b]])->assertOk()
            ->assertJsonPath('can_vote', true);
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$c]])->assertOk()
            ->assertJsonPath('my_choice', [$c])
            ->assertJsonPath('options.0.votes', 0)
            ->assertJsonPath('options.2.votes', 1)
            ->assertJsonPath('total_voters', 1);
        $this->deleteJson('/api/polls/' . $poll['id'] . '/votes')->assertOk()
            ->assertJsonPath('total_voters', 0)
            ->assertJsonPath('my_choice', null);
    }

    /**
     * После срока опроса голосование закрыто; в черновом опросе голосовать нельзя.
     */
    public function test_closed_poll_and_unpublished_article_reject_votes(): void
    {
        $poll = $this->publishedPoll(['options' => ['Да', 'Нет'], 'ends_at' => now()->addMinute()->toIso8601String()]);
        $this->actingAs($this->createUser('voter@example.test'));
        $this->travel(2)->minutes();
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$poll['options'][0]]])
            ->assertJsonValidationErrors('options');
        $this->getJson('/api/articles/' . $poll['article'])
            ->assertJsonPath('poll.closed', true)
            ->assertJsonPath('poll.can_vote', false);

        $draft = $this->publishedPoll(['options' => ['Да', 'Нет']], publish: false);
        $this->actingAs($this->createUser('other@example.test'));
        $this->postJson('/api/polls/' . $draft['id'] . '/votes', ['options' => [$draft['options'][0]]])
            ->assertNotFound();
    }

    /**
     * Открытый опрос показывает десять последних голосовавших и отдаёт полный список постранично.
     */
    public function test_open_poll_shows_ten_recent_voters_and_paginated_list(): void
    {
        $poll = $this->publishedPoll(['options' => ['Да', 'Нет'], 'anonymous' => false]);
        for ($i = 1; $i <= 12; $i++) {
            $this->actingAs($this->createUser("voter{$i}@example.test"));
            $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$poll['options'][0]]])->assertOk();
        }
        $result = $this->getJson('/api/articles/' . $poll['article'])->assertOk()->json('poll.options.0');
        self::assertSame(12, $result['votes']);
        self::assertCount(10, $result['voters']);
        self::assertSame(2, $result['more']);
        self::assertSame('voter12', $result['voters'][0]['name'], 'Сначала последние проголосовавшие.');

        $list = $this->getJson('/api/polls/' . $poll['id'] . '/options/' . $poll['options'][0] . '/voters')
            ->assertOk()
            ->json();
        self::assertCount(12, $list['items']);
        self::assertFalse($list['has_more']);
    }

    /**
     * В анонимном опросе список голосовавших скрыт и от голосовавшего, и от остальных.
     */
    public function test_anonymous_poll_hides_voters(): void
    {
        $poll = $this->publishedPoll(['options' => ['Да', 'Нет']]);
        $this->actingAs($this->createUser('voter@example.test'));
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$poll['options'][1]]])->assertOk()
            ->assertJsonPath('options.1.voters', []);
        $this->getJson('/api/polls/' . $poll['id'] . '/options/' . $poll['options'][1] . '/voters')->assertNotFound();
    }

    /**
     * После первого голоса варианты опроса менять нельзя.
     * Правка без ключа poll сохраняет опрос, значение poll = null удаляет его.
     */
    public function test_options_are_locked_after_votes_and_poll_survives_edits_without_poll_key(): void
    {
        $author = $this->createUser('author@example.test');
        $poll = $this->publishedPoll(['options' => ['Да', 'Нет']], author: $author);
        $this->actingAs($this->createUser('voter@example.test'));
        $this->postJson('/api/polls/' . $poll['id'] . '/votes', ['options' => [$poll['options'][0]]])->assertOk();

        $this->actingAs($author);
        $form = $this->getJson('/api/my/articles/' . $poll['article'])->assertOk()
            ->assertJsonPath('poll.locked', true)
            ->json();
        $edit = ['title' => 'Опрос', 'content' => '<p>Текст</p>'];
        $this->putJson('/api/my/articles/' . $poll['article'], $edit + [
            'version' => $form['version'],
            'poll' => [
                'question' => 'Новый?', 'options' => ['Да', 'Может'],
                'multiple' => false, 'allow_revote' => false, 'anonymous' => true,
            ],
        ])->assertJsonValidationErrors('poll.options');
        $this->putJson('/api/my/articles/' . $poll['article'], $edit + [
            'version' => $form['version'],
            'poll' => [
                'question' => 'Новый вопрос?', 'options' => ['Да', 'Нет'],
                'multiple' => false, 'allow_revote' => false, 'anonymous' => true,
            ],
        ])->assertOk();
        $version = $this->getJson('/api/my/articles/' . $poll['article'])
            ->assertJsonPath('poll.question', 'Новый вопрос?')
            ->json('version');
        $this->putJson('/api/my/articles/' . $poll['article'], $edit + ['version' => $version])->assertOk();
        $version = $this->getJson('/api/my/articles/' . $poll['article'])
            ->assertJsonPath('poll.question', 'Новый вопрос?')
            ->json('version');
        $this->putJson('/api/my/articles/' . $poll['article'], $edit + ['version' => $version, 'poll' => null])
            ->assertOk();
        $this->getJson('/api/my/articles/' . $poll['article'])->assertJsonPath('poll', null);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM poll_ballots'));
    }

    /**
     * Статья с опросом от имени автора; возвращает UUID опроса, id статьи и id вариантов.
     *
     * @param array<string, mixed> $poll
     *
     * @return array{id: string, article: int, options: list<int>}
     */
    private function publishedPoll(array $poll, bool $publish = true, ?User $author = null): array
    {
        $author ??= $this->createUser('author' . bin2hex(random_bytes(3)) . '@example.test');
        $this->actingAs($author);
        $title = 'Опрос ' . bin2hex(random_bytes(3));
        $this->postJson('/api/articles', [
            'title' => $title,
            'content' => '<p>Текст</p>',
            'poll' => ['question' => 'Вопрос?'] + $poll
                + ['multiple' => false, 'allow_revote' => false, 'anonymous' => true],
        ])->assertCreated();
        $id = $this->articleIdByTitle($title);
        if ($publish) {
            $this->em->find(Article::class, $id)->publish();
            $this->em->flush();
        }
        $row = $this->em->getConnection()->fetchAssociative('SELECT id, uuid FROM polls WHERE article_id = ?', [$id]);
        $options = array_map(
            'intval',
            $this->em->getConnection()->fetchFirstColumn(
                'SELECT id FROM poll_options WHERE poll_id = ? ORDER BY position',
                [$row['id']],
            ),
        );

        return ['id' => $row['uuid'], 'article' => $id, 'options' => $options];
    }
}
