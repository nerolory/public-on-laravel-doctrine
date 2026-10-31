<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\Social\SocialAuthService;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Страница завершения регистрации через соцсеть доступна, только если регистрация начата в этой сессии.
 */
final class PendingSocialRegistrationRequest extends FormRequest
{
    /**
     * Регистрация через соцсеть начата в этой сессии.
     */
    public function authorize(SocialAuthService $auth): Response
    {
        return $auth->hasPending() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Полей у запроса нет.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
