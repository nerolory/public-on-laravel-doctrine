<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Media\StoreImageRequest;
use App\Services\Media\ImageService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Загрузка картинок из редактора.
 */
final class ImageController extends Controller
{
    /**
     * @param ResponseFactory $responses  ответы
     * @param Redirector      $redirector перенаправления
     * @param ImageService    $images     обработка картинок
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ImageService $images,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Сохраняет картинку во всех ширинах и возвращает её адреса.
     */
    public function store(StoreImageRequest $request): JsonResponse
    {
        $image = $this->images->store($request->file('file'), $request->purpose(), $request->user());

        return $this->responses->json($this->images->present($image), Response::HTTP_CREATED);
    }
}
