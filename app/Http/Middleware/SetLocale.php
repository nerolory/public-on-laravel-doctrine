<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Localization\LocalizationService;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выставляет язык запроса. Фронтенд определяет язык по адресу (префикс или поддомен) и передаёт его
 * в заголовке X-Locale; выключенный или неизвестный язык заменяется языком по умолчанию.
 */
final class SetLocale
{
    /** Заголовок с кодом языка страницы. */
    public const string HEADER = 'X-Locale';

    /**
     * @param LocalizationService $localization языки сайта
     * @param Application         $app          приложение: язык переводов
     */
    public function __construct(
        private readonly LocalizationService $localization,
        private readonly Application $app,
    ) {
    }

    /**
     * Применяет язык к переводам и отдаёт его код в заголовке Content-Language.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = $this->localization->resolve($request->header(self::HEADER));
        $this->app->setLocale($language->file);

        $response = $next($request);
        $response->headers->set('Content-Language', $language->code);

        return $response;
    }
}
