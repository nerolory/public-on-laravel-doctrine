<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\CompleteSocialRegistrationRequest;
use App\Http\Requests\Auth\OAuthProviderRequest;
use App\Http\Requests\Auth\PendingSocialRegistrationRequest;
use App\Http\Requests\Auth\TelegramLoginRequest;
use App\Services\Social\SocialAuthService;
use App\Services\Social\SocialProviders;
use App\Support\Social\TelegramAuthenticator;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Вход и регистрация через соцсети: OAuth через Socialite и Telegram с проверкой подписи.
 */
final class SocialAuthController extends Controller
{
    /**
     * @param ResponseFactory       $responses  ответы
     * @param Redirector            $redirector перенаправления
     * @param SocialProviders       $providers  провайдеры входа
     * @param SocialAuthService     $auth       вход и регистрация
     * @param TelegramAuthenticator $telegram   проверка данных входа Telegram
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly SocialProviders $providers,
        private readonly SocialAuthService $auth,
        private readonly TelegramAuthenticator $telegram,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Включённые провайдеры и адреса входа для кнопок на страницах входа и регистрации.
     */
    public function index(): JsonResponse
    {
        return $this->responses->json(['items' => $this->providers->links()]);
    }

    /**
     * Переход на страницу входа провайдера.
     */
    public function redirect(OAuthProviderRequest $request): RedirectResponse
    {
        return $this->redirector->away($this->auth->authorizationUrl($request->provider()));
    }

    /**
     * Возврат от провайдера: вход, ожидание согласий или ошибка.
     */
    public function callback(OAuthProviderRequest $request): RedirectResponse
    {
        $result = $this->auth->loginFromCallback($request->provider());
        $request->session()->regenerate();

        return $this->redirector->to($result->redirectPath());
    }

    /**
     * Фронтенд передаёт сюда данные из фрагмента #tgAuthResult после возврата с oauth.telegram.org.
     */
    public function telegram(TelegramLoginRequest $request): JsonResponse
    {
        $result = $this->auth->handle($this->telegram->identity($request->loginData()));
        $request->session()->regenerate();

        return $this->responses->json(['next' => $result->value]);
    }

    /**
     * Данные отложенной регистрации: имя и email из соцсети для формы согласий.
     */
    public function pending(PendingSocialRegistrationRequest $request): JsonResponse
    {
        $identity = $this->auth->pendingIdentity();

        return $this->responses->json(
            ['provider' => $identity->provider, 'name' => $identity->name, 'email' => $identity->email],
        );
    }

    /**
     * Завершает регистрацию после принятия согласий.
     */
    public function complete(CompleteSocialRegistrationRequest $request): JsonResponse
    {
        $this->auth->complete(
            $this->auth->pullPending(),
            $request->email(),
            $request->acceptedConsents(),
            $request->ip(),
            $request->userAgent(),
        );
        $request->session()->regenerate();

        return $this->responses->json(['message' => __('messages.auth.registered')], Response::HTTP_CREATED);
    }
}
