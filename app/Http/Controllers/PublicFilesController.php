<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Seo\PublicFilesService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * /robots.txt, /sitemap.xml и /rss.xml: nginx передаёт их в Laravel.
 */
final class PublicFilesController extends Controller
{
    /**
     * @param ResponseFactory    $responses  ответы
     * @param Redirector         $redirector перенаправления
     * @param PublicFilesService $files      содержимое файлов
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly PublicFilesService $files,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * robots.txt.
     */
    public function robots(): Response
    {
        return $this->responses->make(
            $this->files->robots(),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    /**
     * Карта сайта.
     */
    public function sitemap(): BinaryFileResponse
    {
        return $this->responses->file(
            $this->files->sitemapFile(),
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }

    /**
     * RSS-лента последних статей; включена ли она, проверяет SitePolicy на маршруте.
     */
    public function rss(Request $request): Response
    {
        return $this->files->feed()->toResponse($request);
    }
}
