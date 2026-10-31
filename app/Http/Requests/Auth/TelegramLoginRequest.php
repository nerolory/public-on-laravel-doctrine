<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTO\Social\TelegramLoginData;
use App\Services\Social\SocialProviders;
use App\Support\Social\TelegramAuthenticator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Данные из фрагмента #tgAuthResult после возврата с oauth.telegram.org.
 */
final class TelegramLoginRequest extends FormRequest
{
    /**
     * Правила: данные приходят объектом с идентификатором, временем входа и подписью.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.id' => ['required', 'integer'],
            'data.auth_date' => ['required', 'integer'],
            'data.hash' => ['required', 'string'],
            'data.first_name' => ['nullable', 'string'],
            'data.last_name' => ['nullable', 'string'],
        ];
    }

    /**
     * Подпись данных совпадает, и они не устарели; иначе ошибка поля data.
     *
     * @return list<callable(Validator): void>
     */
    public function after(TelegramAuthenticator $telegram, SocialProviders $providers): array
    {
        return [function (Validator $validator) use ($telegram, $providers): void {
            if (
                $validator->errors()->isEmpty()
                && ! $telegram->isValid($this->loginData(), $providers->telegramBotToken())
            ) {
                $validator->errors()->add('data', __('social.failed'));
            }
        }];
    }

    /**
     * Данные входа: подпись считается по всем скалярным полям, кроме hash.
     */
    public function loginData(): TelegramLoginData
    {
        $fields = collect($this->input('data'))
            ->filter(static fn (mixed $value): bool => is_scalar($value))
            ->map(static fn (string|int|float|bool $value): string => (string) $value);

        return new TelegramLoginData(
            id: $fields->get('id'),
            firstName: $fields->get('first_name'),
            lastName: $fields->get('last_name'),
            authDate: (int) $fields->get('auth_date'),
            hash: $fields->get('hash'),
            signedFields: $fields->except('hash'),
        );
    }
}
