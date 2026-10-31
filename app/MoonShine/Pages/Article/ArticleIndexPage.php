<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Article;

use App\DTO\Admin\AdminArticleRowData;
use App\DTO\Admin\AdminOptionData;
use App\DTO\Article\ArticleModerationRowData;
use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationStatus;
use App\Services\Article\ArticleFeedService;
use App\Support\Settings\PageSizes;
use App\Traits\PaginatesList;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use App\Traits\ShowsModeration;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;

/**
 * Модерация статей: статус меняется сразу из списка, отклонение требует причину.
 */
final class ArticleIndexPage extends Page
{
    use PaginatesList;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;
    use ShowsModeration;

    /** Разделитель разделов в строке статьи. */
    private const string SECTION_SEPARATOR = ' · ';

    /** Адрес страницы в админке: /admin/page/articles. */
    protected ?string $alias = 'articles';

    /**
     * @param CoreContract       $core      ядро MoonShine
     * @param ArticleFeedService $feed      списки статей
     * @param PageSizes          $pageSizes размер страницы списка
     * @param ViewFactory        $views     шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly ArticleFeedService $feed,
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
        return __('admin.articles.title');
    }

    /**
     * Хлебные крошки.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs(__('admin.menu.articles'));
    }

    /**
     * Список статей и постраничная навигация.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $page = $this->pageNumber();
        $articles = $this->feed->moderationPage($page);

        return $this->listComponents(
            $this->views->make('admin.articles', [
                'articles' => $articles->items->map($this->row(...)),
                'bulkActions' => $this->bulkActions(),
                'createUrl' => $this->adminPageUrl(ArticleFormPage::class),
                'reasonLength' => ModerationStatus::REASON_LENGTH,
                'reasonRows' => self::REJECT_REASON_ROWS,
            ]),
            $articles,
            $page,
            $this->pageSizes->admin(),
        );
    }

    /**
     * Строка таблицы из записи модерации.
     */
    private function row(ArticleModerationRowData $article): AdminArticleRowData
    {
        $status = $article->currentAction->value;

        return new AdminArticleRowData(
            id: $article->id,
            title: $article->title,
            excerpt: $article->excerpt,
            authorName: $this->displayAuthorName($article->author),
            sections: $article->sections->implode(self::SECTION_SEPARATOR),
            viewCount: $article->viewCount,
            status: $status,
            rejectionReason: $status === ModerationAction::Reject->value ? $article->rejectionReason : null,
            editUrl: $this->adminPageUrl(ArticleFormPage::class, ['id' => $article->id]),
            statusOptions: $this->statusOptions($status),
        );
    }

    /**
     * Действия для массового выбора.
     *
     * @return Collection<int, AdminOptionData>
     */
    private function bulkActions(): Collection
    {
        return collect(ModerationAction::cases())->map(
            static fn (ModerationAction $action): AdminOptionData => new AdminOptionData(
                value: $action->value,
                label: __('admin.articles.actions.' . $action->value),
            ),
        );
    }
}
