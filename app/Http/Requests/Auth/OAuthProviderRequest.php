<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\Social\SocialProvider;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Провайдер входа через соцсеть из адреса маршрута.
 */
final class OAuthProviderRequest extends FormRequest
{
    /**
     * Доступ проверяется маршрутом: провайдер из адреса лишь выбирает способ входа.
     */
    public function authorize(): bool
    {
        return true;
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

    /**
     * Провайдер из адреса.
     */
    public function provider(): SocialProvider
    {
        return SocialProvider::from((string) $this->route('provider'));
    }
}
