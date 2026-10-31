<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Entities\User;
use App\Services\Legal\ConsentService;
use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Запрещает публиковать статьи, комментарии и картинки, пока пользователь не принял
 * текущие версии обязательных документов. Ответ 409 содержит тексты, которые нужно принять.
 */
final class EnsureConsentsAccepted
{
    /** Код ответа, по которому фронтенд открывает окно принятия правил. */
    public const string CODE = 'consents_required';

    /**
     * @param ConsentService  $consents   согласия
     * @param ResponseFactory $responses  ответы с телом и заголовками
     * @param Redirector      $redirector перенаправления
     */
    public function __construct(
        private readonly ConsentService $consents,
        private readonly ResponseFactory $responses,
        private readonly Redirector $redirector,
    ) {
    }

    /**
     * Пропускает запрос, если обязательные документы приняты.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $pending = $user instanceof User ? $this->consents->pending($user) : collect();

        if ($pending->isEmpty()) {
            return $next($request);
        }

        if (! $request->expectsJson()) {
            return $this->redirector->back()->withErrors(['consents' => __('legal.consents_required')]);
        }

        return $this->responses->json(
            ['message' => __('legal.consents_required'), 'code' => self::CODE, 'documents' => $pending->toArray()],
            Response::HTTP_CONFLICT,
        );
    }
}
