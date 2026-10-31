<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AuthenticateUser;
use App\Actions\LogoutUser;
use App\Actions\RegisterUser;
use App\Contracts\AccountSelfDeletion;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\User\DeleteAccountRequest;
use App\Http\Requests\User\UpdateAccountRequest;
use App\Services\User\UserService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Регистрация, вход, выход, смена email и удаление своей учётной записи.
 */
final class UserController extends Controller
{
    /**
     * @param ResponseFactory     $responses      ответы
     * @param Redirector          $redirector     перенаправления
     * @param RegisterUser        $registration   регистрация
     * @param AuthenticateUser    $authentication вход
     * @param LogoutUser          $logout         выход
     * @param AccountSelfDeletion $deletion       удаление учётной записи
     * @param UserService         $users          учётные записи
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly RegisterUser $registration,
        private readonly AuthenticateUser $authentication,
        private readonly LogoutUser $logout,
        private readonly AccountSelfDeletion $deletion,
        private readonly UserService $users,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Регистрация с согласиями; пользователь сразу входит.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $this->registration->execute(
            $request->toData(),
            $request->password(),
            $request->acceptedConsents(),
            $request->ip(),
            $request->userAgent(),
        );
        $request->session()->regenerate();

        return $this->responses->json(['message' => __('messages.auth.registered')], Response::HTTP_CREATED);
    }

    /**
     * Вход по email и паролю; неверные данные или блокировка дают ошибку поля email.
     *
     * @throws ValidationException неверные данные или заблокированный пользователь
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! $this->authentication->execute($request->email(), $request->password())) {
            throw ValidationException::withMessages(['email' => __('messages.auth.failed')]);
        }

        $request->session()->regenerate();

        return $this->responses->json(['message' => __('messages.auth.logged_in')]);
    }

    /**
     * Выход.
     */
    public function logout(): JsonResponse
    {
        $this->logout->execute();

        return $this->responses->json(['message' => __('messages.auth.logged_out')]);
    }

    /**
     * Смена email и имени; на новый адрес уходит письмо подтверждения.
     */
    public function update(UpdateAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->users->update($user, $request->toData($user));

        return $this->responses->json(['message' => __('messages.account.updated')]);
    }

    /**
     * Удаление своей учётной записи с отзывом выбранных согласий.
     */
    public function delete(DeleteAccountRequest $request): JsonResponse
    {
        $this->deletion->deleteOwnAccount(
            $request->user(),
            $request->revokedConsents(),
            $request->ip(),
            $request->userAgent(),
        );
        $this->logout->execute();

        return $this->responses->json(['message' => __('messages.account.deleted')]);
    }
}
