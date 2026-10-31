<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\User\EmailVerificationService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

/**
 * Подтверждение email: переход по ссылке из письма и повторная отправка.
 */
final class EmailVerificationController extends Controller
{
    /**
     * @param ResponseFactory          $responses    ответы
     * @param Redirector               $redirector   перенаправления
     * @param EmailVerificationService $verification подтверждение email
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly EmailVerificationService $verification,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Переход по ссылке из письма: подпись и срок проверяет middleware signed.
     */
    public function verify(int $id, string $hash): RedirectResponse
    {
        $verified = $this->verification->verify($id, $hash);

        return $this->redirector->to($this->verification->verifiedRedirectUrl($verified));
    }

    /**
     * Отправляет письмо со ссылкой ещё раз.
     */
    public function resend(Request $request): JsonResponse
    {
        $this->verification->send($request->user());

        return $this->responses->json(['message' => __('mail.verify.sent')]);
    }
}
