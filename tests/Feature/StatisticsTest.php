<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Role;
use Tests\DoctrineTestCase;

/**
 * Статистика в админке: доступна администраторам, считает просмотры по дням и регистрации.
 */
final class StatisticsTest extends DoctrineTestCase
{
    /**
     * Статистика открыта администратору и не открыта модератору; просмотры считаются по дням.
     */
    public function test_statistics_are_for_administrators_and_count_views_by_day(): void
    {
        $article = $this->createArticle($this->createUser('author@example.test'), 'Читаемая', '<p>Т</p>');
        foreach (['Mozilla/5.0 Chrome/140.0', 'Mozilla/5.0 Firefox/140.0'] as $agent) {
            $this->post(
                '/api/articles/' . $article->getId() . '/view',
                [],
                ['User-Agent' => $agent, 'Referer' => 'https://ya.ru/'],
            )
                ->assertNoContent();
        }

        $this->actingAsRole(Role::MODERATOR);
        $this->get('/admin/page/statistics')->assertForbidden();
        $this->flushSession();
        $this->actingAsRole(Role::ADMINISTRATOR, 'admin@example.test');
        $html = $this->get('/admin/page/statistics?days=7')->assertOk()
            ->assertSeeInOrder(['Читаемая', '— 2'])
            ->assertSee('ya.ru — 2')
            ->getContent();
        self::assertStringContainsString('vendor/moonshine-apexcharts/apexcharts.js', $html);
        self::assertStringContainsString('Просмотры и посетители по дням', $html);
        self::assertStringContainsString('Регистрации, публикации и комментарии по дням', $html);
        self::assertMatchesRegularExpression('#<dt>Просмотры</dt><dd>2</dd>#', $html);
        self::assertMatchesRegularExpression(
            '#<dt>Регистрации</dt><dd>3</dd>#',
            $html,
            'Автор, модератор и администратор зарегистрированы сегодня.',
        );
    }
}
