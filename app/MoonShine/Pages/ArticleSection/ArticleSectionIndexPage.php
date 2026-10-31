<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\ArticleSection;

use App\DTO\Admin\AdminSectionRowData;
use App\DTO\ArticleSection\SectionRowData;
use App\Services\Article\ArticleSectionService;
use App\Support\Settings\PageSizes;
use App\Traits\PaginatesList;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Illuminate\Contracts\View\Factory as ViewFactory;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;

/**
 * Список разделов статей деревом с числом статей.
 */
final class ArticleSectionIndexPage extends Page
{
    use PaginatesList;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Максимальный уровень отступа в списке: глубже отступ не растёт. */
    private const int MAX_INDENT_LEVEL = 4;

    /** Адрес страницы в админке: /admin/page/article-sections. */
    protected ?string $alias = 'article-sections';

    /**
     * @param CoreContract          $core      ядро MoonShine
     * @param ArticleSectionService $sections  разделы
     * @param PageSizes             $pageSizes размер страницы списка
     * @param ViewFactory           $views     шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly ArticleSectionService $sections,
        private readonly PageSizes $pageSizes,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.sections.title');
    }

    /**
     * Хлебные крошки.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs($this->getTitle());
    }

    /**
     * Список разделов и постраничная навигация.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $page = $this->pageNumber();
        $limit = $this->pageSizes->admin();
        $sections = $this->sections->page($page, $limit);

        return $this->listComponents(
            $this->views->make('admin.article-sections', [
                'sections' => $sections->items->map($this->row(...)),
                'createUrl' => $this->adminPageUrl(ArticleSectionFormPage::class),
            ]),
            $sections,
            $page,
            $limit,
        );
    }

    /**
     * Строка дерева: отступ, число статей и действия.
     */
    private function row(SectionRowData $section): AdminSectionRowData
    {
        $node = $section->node;
        $isChild = $node->level > 0;

        return new AdminSectionRowData(
            id: $node->id,
            name: $node->name,
            slug: $node->slug,
            displayName: $isChild ? __('admin.sections.child_name', ['name' => $node->name]) : $node->name,
            levelClass: 'article-admin__section--level-' . min($node->level, self::MAX_INDENT_LEVEL),
            articleCount: $section->articleCount,
            deletable: $section->deletable,
            editUrl: $this->adminPageUrl(ArticleSectionFormPage::class, ['id' => $node->id]),
            deleteTitle: __($section->deletable ? 'admin.sections.delete' : 'admin.sections.root_not_empty'),
            deleteConfirm: __(
                $isChild ? 'admin.sections.confirm_delete_child' : 'admin.sections.confirm_delete',
                ['name' => $node->name],
            ),
        );
    }
}
