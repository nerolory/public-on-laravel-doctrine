<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Common\SectionFilterRequest;
use App\Services\Seo\SeoService;
use App\Services\Seo\SiteConfigService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;

/**
 * Публичные настройки сайта и теги <head> для главной и разделов; у статьи теги приходят вместе с самой статьёй.
 */
final class SeoController extends Controller
{
    /** Время жизни ответа с настройками сайта в кеше браузера и прокси в секундах. */
    private const int CACHE_SECONDS = 60;

    /**
     * @param ResponseFactory   $responses  ответы
     * @param Redirector        $redirector перенаправления
     * @param SeoService        $seo        теги <head>
     * @param SiteConfigService $site       публичные настройки
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly SeoService $seo,
        private readonly SiteConfigService $site,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Публичные настройки для фронтенда: название сайта, счётчики и лимиты форм.
     */
    public function site(): JsonResponse
    {
        return $this->responses->json($this->site->public())->setPublic()->setMaxAge(self::CACHE_SECONDS);
    }

    /**
     * Теги главной или раздела из параметра ?section=.
     */
    public function home(SectionFilterRequest $request): JsonResponse
    {
        return $this->responses->json($this->seo->homeOrSection($request->section()));
    }
}
