<?php

declare(strict_types=1);

use App\Console\Commands\GenerateSitemap;
use App\Console\Commands\PruneArticleViews;
use App\Http\Middleware\RejectBannedUser;
use App\Http\Middleware\SetLocale;
use App\Support\Seo\SiteUrls;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LaravelDoctrine\ORM\Middleware\SubstituteBindings as SubstituteEntityBindings;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SubstituteEntityBindings::class,
            SetLocale::class,
            RejectBannedUser::class,
        ]);
        // Сущности из параметров маршрута подставляются до проверки прав middleware can.
        $middleware->prependToPriorityList(Authorize::class, SubstituteEntityBindings::class);
        $middleware->redirectGuestsTo(SiteUrls::LOGIN_PATH);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(PruneArticleViews::class)->dailyAt(PruneArticleViews::DAILY_AT);
        $schedule->command(GenerateSitemap::class)->dailyAt(GenerateSitemap::DAILY_AT);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Записи нет — в параметре маршрута или в выборке сервиса: ответ 404.
        $exceptions->map(
            EntityNotFoundException::class,
            static fn (EntityNotFoundException $exception): NotFoundHttpException => new NotFoundHttpException(
                __('messages.not_found'),
                $exception,
            ),
        );
        // Оптимистическая блокировка: статью изменили, пока форма была открыта; в журнал ошибок не пишется.
        $exceptions->dontReport(OptimisticLockException::class);
        $exceptions->render(
            static function (OptimisticLockException $exception, Request $request): JsonResponse|RedirectResponse {
                $message = __('messages.articles.version_conflict');

                if ($request->expectsJson()) {
                    return new JsonResponse(
                        ['message' => $message, 'code' => 'version_conflict'],
                        status: Response::HTTP_CONFLICT,
                    );
                }

                return back()->withInput()->withErrors(['version' => $message]);
            },
        );
    })->create();
