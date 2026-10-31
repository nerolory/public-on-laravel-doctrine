<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Article\ArticleSectionService;
use Tests\DoctrineTestCase;

/**
 * Фильтр разделов на главной: строки фильтра следуют выбранному пути по дереву разделов.
 */
final class SectionFilterTest extends DoctrineTestCase
{
    /**
     * Строки фильтра показывают корневые разделы; при выборе раздела показываются его путь и подразделы.
     */
    public function test_filter_rows_follow_the_selected_path(): void
    {
        $sections = $this->app->make(ArticleSectionService::class);
        $tech = $sections->create('Технологии');
        $dev = $sections->create('Разработка', $tech->getId());
        $sections->create('Бэкенд', $dev->getId());
        $sections->create('Прогулки');

        $rows = $this->getJson('/api/sections/filter')->assertOk()->json('rows');
        self::assertCount(1, $rows);
        self::assertSame(['Прогулки', 'Технологии'], array_column($rows[0]['items'], 'name'));

        $rows = $this->getJson('/api/sections/filter?section=' . $dev->getSlug())->assertOk()->json('rows');
        self::assertSame(['root', $tech->getSlug(), $dev->getSlug()], array_column($rows, 'key'));
        self::assertSame([$tech->getSlug(), $dev->getSlug(), null], array_column($rows, 'active'));
        self::assertSame(['all', 'whole_section', 'whole_section'], array_column($rows, 'all_label'));
        self::assertSame(['Бэкенд'], array_column($rows[2]['items'], 'name'));

        self::assertCount(1, $this->getJson('/api/sections/filter?section=net-takogo')->assertOk()->json('rows'));
    }
}
