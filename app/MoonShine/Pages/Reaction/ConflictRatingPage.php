<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Reaction;

use App\DTO\Admin\AdminConflictRowData;
use App\DTO\Reaction\ConflictEntryData;
use App\Enums\User\Ability;
use App\Services\Reaction\ReactionService;
use App\Support\Seo\SiteUrls;
use App\Traits\AuthorizesAdminPage;
use App\Traits\ResolvesAdminUrls;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\Factory as ViewFactory;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FlexibleRender;

/**
 * «Рейтинг конфликтности» — только для владельца и администраторов.
 */
final class ConflictRatingPage extends Page
{
    use AuthorizesAdminPage;
    use ResolvesAdminUrls;

    /** Адрес страницы в админке: /admin/page/conflict-rating. */
    protected ?string $alias = 'conflict-rating';

    /**
     * @param CoreContract    $core      ядро MoonShine
     * @param ReactionService $reactions рейтинги реакций
     * @param Gate            $gate      права доступа
     * @param ViewFactory     $views     шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly ReactionService $reactions,
        private readonly Gate $gate,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.conflicts.title');
    }

    /**
     * Хлебные крошки: админка и текущая страница.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs($this->getTitle());
    }

    /**
     * Право на открытие страницы: управление пользователями.
     */
    protected function ability(): Ability
    {
        return Ability::ManageUsers;
    }

    /**
     * Рейтинги конфликтных статей и комментариев.
     *
     * @return iterable<FlexibleRender>
     */
    protected function components(): iterable
    {
        return [FlexibleRender::make($this->views->make('admin.conflicts', [
            'articles' => $this->reactions->articleConflicts()->map($this->row(...)),
            'comments' => $this->reactions->commentConflicts()->map($this->row(...)),
        ]))];
    }

    /**
     * Строка рейтинга с публичным адресом статьи.
     */
    private function row(ConflictEntryData $entry): AdminConflictRowData
    {
        return new AdminConflictRowData(
            articleTitle: $entry->articleTitle,
            articleUrl: SiteUrls::articlePath($entry->articleId),
            excerpt: $entry->excerpt,
            positive: $entry->score->positive,
            negative: $entry->score->negative,
            conflict: $entry->score->conflict,
        );
    }
}
