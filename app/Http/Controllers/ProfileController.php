<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\User;
use App\Http\Requests\Common\PageRequest;
use App\Http\Requests\User\UpdateAvatarRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Services\User\ProfileService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

/**
 * Профиль: сессия и аккаунт владельца, анкета, аватар и публичная карточка автора.
 */
final class ProfileController extends Controller
{
    /**
     * @param ResponseFactory $responses  ответы
     * @param Redirector      $redirector перенаправления
     * @param ProfileService  $profiles   профили пользователей
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ProfileService $profiles,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Состояние сессии без ошибки 401 для гостя: фронтенд узнаёт, вошёл ли посетитель.
     */
    public function session(Request $request): JsonResponse
    {
        $account = $this->profiles->sessionAccount($request->user())?->toArray();

        return $this->responses->json(['user' => $account]);
    }

    /**
     * Аккаунт текущего пользователя.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->responses->json($this->profiles->account($request->user()));
    }

    /**
     * Сохраняет анкету.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $this->profiles->update($request->user(), $request->profileData());

        return $this->saved($request, 'profile.saved');
    }

    /**
     * Загружает новый аватар.
     */
    public function avatar(UpdateAvatarRequest $request): JsonResponse
    {
        $this->profiles->changeAvatar($request->user(), $request->image());

        return $this->saved($request, 'profile.avatar_saved');
    }

    /**
     * Удаляет аватар.
     */
    public function removeAvatar(Request $request): JsonResponse
    {
        $this->profiles->removeAvatar($request->user());

        return $this->saved($request, 'profile.avatar_removed');
    }

    /**
     * Публичная карточка автора со страницей его статей.
     */
    public function author(User $user, PageRequest $request): JsonResponse
    {
        return $this->responses->json($this->profiles->publicProfile($user, $request->page()));
    }

    /**
     * Сообщение об успехе и обновлённый аккаунт.
     */
    private function saved(Request $request, string $message): JsonResponse
    {
        $account = $this->profiles->account($request->user())->toArray();

        return $this->responses->json(['message' => __($message), 'user' => $account]);
    }
}
