<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Album;

use App\DTO\Admin\AdminAlbumRowData;
use App\DTO\Album\AlbumModerationRowData;
use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationStatus;
use App\Services\Album\AlbumService;
use App\Support\Seo\SiteUrls;
use App\Support\Settings\PageSizes;
use App\Traits\PaginatesList;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use App\Traits\ShowsModeration;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;

/**
 * Модерация альбомов: статус меняется сразу из списка, отклонение требует причину.
 */
final class AlbumIndexPage extends Page
{
    use PaginatesList;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;
    use ShowsModeration;

    /** Адрес страницы в админке: /admin/page/albums. */
    protected ?string $alias = 'albums';

    /**
     * @param CoreContract $core       ядро MoonShine
     * @param AlbumService $albums     альбомы
     * @param PageSizes    $pageSizes  размер страницы списка
     * @param ViewFactory  $views      шаблоны
     * @param Translator   $translator перевод с склонением
     */
    public function __construct(
        CoreContract $core,
        private readonly AlbumService $albums,
        private readonly PageSizes $pageSizes,
        private readonly ViewFactory $views,
        private readonly Translator $translator,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.albums.title');
    }

    /**
     * Хлебные крошки.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs(__('admin.menu.albums'));
    }

    /**
     * Список альбомов и постраничная навигация.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $page = $this->pageNumber();
        $albums = $this->albums->moderationPage($page);

        return $this->listComponents(
            $this->views->make('admin.albums', [
                'albums' => $albums->items->map($this->row(...)),
                'reasonLength' => ModerationStatus::REASON_LENGTH,
                'reasonRows' => self::REJECT_REASON_ROWS,
            ]),
            $albums,
            $page,
            $this->pageSizes->admin(),
        );
    }

    /**
     * Строка таблицы из записи модерации.
     */
    private function row(AlbumModerationRowData $album): AdminAlbumRowData
    {
        $status = ModerationAction::leadingTo($album->status)->value;

        return new AdminAlbumRowData(
            uuid: $album->uuid,
            title: $album->title,
            photosLabel: $this->translator->choice('admin.albums.photos', $album->photos, ['count' => $album->photos]),
            ownerName: $this->displayAuthorName($album->owner),
            status: $status,
            rejectionReason: $album->rejectionReason,
            openUrl: SiteUrls::albumPath($album->uuid),
            statusOptions: $this->statusOptions($status),
        );
    }
}
