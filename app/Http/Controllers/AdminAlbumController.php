<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Admin\AdminPage;
use App\Http\Requests\Album\ModerateAlbumsRequest;
use App\Services\Album\AlbumService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Модерация альбомов из админки.
 */
final class AdminAlbumController extends Controller
{
    /**
     * @param ResponseFactory $responses  ответы
     * @param Redirector      $redirector перенаправления
     * @param AlbumService    $albums     альбомы
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly AlbumService $albums,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Меняет состояние модерации выбранных альбомов и возвращает к списку.
     */
    public function moderate(ModerateAlbumsRequest $request): RedirectResponse
    {
        $this->albums->moderate($request->uuids(), $request->action(), $request->reason());

        return $this->backToAdminPage(AdminPage::Albums, __('admin.albums.moderated'));
    }
}
