<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Localization\LocalizationService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;

/**
 * Языки и переводы для фронтенда.
 */
final class LocalizationController extends Controller
{
    /** Время жизни ответа в кеше браузера и прокси в секундах. */
    private const int CACHE_SECONDS = 60;

    /**
     * @param ResponseFactory     $responses    ответы
     * @param Redirector          $redirector   перенаправления
     * @param LocalizationService $localization языки сайта
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly LocalizationService $localization,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Режим мультиязычности и включённые языки: фронтенд строит по ним адреса и переключатель языка.
     */
    public function config(): JsonResponse
    {
        return $this->responses->json($this->localization->config())->setPublic()->setMaxAge(self::CACHE_SECONDS);
    }

    /**
     * Переводы интерфейса для языка.
     */
    public function messages(string $code): JsonResponse
    {
        return $this->responses->json($this->localization->messages($code))
            ->setPublic()
            ->setMaxAge(self::CACHE_SECONDS);
    }
}
