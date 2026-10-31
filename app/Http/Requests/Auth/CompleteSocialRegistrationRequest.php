<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\Legal\ConsentService;
use App\Services\Social\SocialAuthService;
use App\Services\User\UserService;
use App\Traits\AcceptsConsents;
use App\Traits\ValidatesAccountFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Завершение регистрации через соцсеть: согласия региона и email, если соцсеть его не передала.
 */
final class CompleteSocialRegistrationRequest extends FormRequest
{
    use AcceptsConsents;
    use ValidatesAccountFields;

    /**
     * Завершить можно только начатую регистрацию через соцсеть.
     */
    public function authorize(SocialAuthService $auth): bool
    {
        return $auth->hasPending();
    }

    /**
     * Правила проверки email и согласий.
     *
     * @return array<string, mixed>
     */
    public function rules(UserService $users, ConsentService $consents): array
    {
        return [
            'email' => $this->emailRules($users),
            ...$this->consentRules($consents),
        ];
    }

    /**
     * Адрес почты нового пользователя.
     */
    public function email(): string
    {
        return (string) $this->validated('email');
    }
}
