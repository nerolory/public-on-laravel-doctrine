<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\DTO\User\UserData;
use App\Entities\User;
use App\Services\User\UserService;
use App\Traits\ValidatesAccountFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Смена email и имени владельцем аккаунта. Без поля name меняется только email: имя правится в профиле.
 */
final class UpdateAccountRequest extends FormRequest
{
    use ValidatesAccountFields;

    /**
     * Правила проверки имени и email.
     *
     * @return array<string, mixed>
     */
    public function rules(UserService $users): array
    {
        return [
            'name' => ['sometimes', ...$this->personNameRules()],
            'email' => $this->emailRules($users, $this->currentUserId()),
        ];
    }

    /**
     * Новые имя и email; без поля name остаётся текущее имя.
     */
    public function toData(User $user): UserData
    {
        $name = $this->has('name') ? (string) $this->validated('name') : (string) $user->getFullName()->firstName();

        return new UserData($name, (string) $this->validated('email'));
    }

    /**
     * Номер пользователя, который меняет адрес; null для гостя.
     */
    private function currentUserId(): ?int
    {
        $user = $this->user();

        return $user instanceof User ? $user->persistedId() : null;
    }
}
