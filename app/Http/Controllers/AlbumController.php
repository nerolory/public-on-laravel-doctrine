<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\Album;
use App\Http\Requests\Album\StoreAlbumRequest;
use App\Http\Requests\Common\PageRequest;
use App\Services\Album\AlbumService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Альбомы на сайте: публичный просмотр и «Мои альбомы» для владельца.
 */
final class AlbumController extends Controller
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
     * Страница альбома.
     */
    public function show(Album $album, Request $request): JsonResponse
    {
        return $this->responses->json($this->albums->show($album, $request->user()));
    }

    /**
     * Страница «Моих альбомов».
     */
    public function mine(PageRequest $request): JsonResponse
    {
        return $this->responses->json($this->albums->ownedBy($request->user(), $request->page()));
    }

    /**
     * Создаёт альбом и отправляет его на модерацию.
     */
    public function store(StoreAlbumRequest $request): JsonResponse
    {
        $album = $this->albums->create($request->albumData(), $request->user());

        return $this->submittedResponse($album, Response::HTTP_CREATED);
    }

    /**
     * Сохраняет правку альбома и возвращает его на модерацию.
     */
    public function update(Album $album, StoreAlbumRequest $request): JsonResponse
    {
        $album = $this->albums->update($album, $request->albumData());

        return $this->submittedResponse($album, Response::HTTP_OK);
    }

    /**
     * Удаляет свой альбом.
     */
    public function destroy(Album $album): JsonResponse
    {
        $this->albums->delete([$album]);

        return $this->responses->json(['message' => __('messages.albums.deleted')]);
    }

    /**
     * Альбом на модерации с сообщением о том, что он отправлен.
     */
    private function submittedResponse(Album $album, int $status): JsonResponse
    {
        $payload = $this->albums->present($album, withModeration: true)->toArray();

        return $this->responses->json([...$payload, 'message' => __('messages.albums.submitted')], $status);
    }
}
