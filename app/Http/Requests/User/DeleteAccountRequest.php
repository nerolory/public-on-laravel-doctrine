<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\Legal\ConsentType;
use App\Services\Legal\ConsentService;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Удаление учётной записи с подтверждением пароля и выбором согласий, которые нужно отозвать.
 */
final class DeleteAccountRequest extends FormRequest
{
    /**
     * Удалить себя может каждый, кроме владельца сайта (UserPolicy).
     */
    public function authorize(Gate $gate): Response
    {
        return $gate->inspect('delete', $this->user());
    }

    /**
     * Правила проверки пароля и списка согласий.
     *
     * @return array<string, mixed>
     */
    public function rules(ConsentService $consents): array
    {
        return [
            'password' => ['required', 'current_password'],
            'revoke' => ['array'],
            'revoke.*' => ['distinct', Rule::in($consents->availableTypes()->all())],
        ];
    }

    /**
     * Согласия, которые пользователь отзывает вместе с удалением.
     *
     * @return Collection<int, ConsentType>
     */
    public function revokedConsents(): Collection
    {
        return ConsentType::fromValues($this->collect('revoke'));
    }
}
