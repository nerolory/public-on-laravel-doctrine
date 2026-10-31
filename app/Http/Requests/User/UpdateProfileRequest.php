<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\DTO\User\ContactsData;
use App\DTO\User\ProfileData;
use App\Entities\PersonName;
use App\Entities\User;
use App\Enums\User\Gender;
use App\Enums\User\ProfileField;
use App\Services\User\ProfileService;
use App\Traits\ValidatesAccountFields;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Анкета профиля из личного кабинета.
 */
final class UpdateProfileRequest extends FormRequest
{
    use ValidatesAccountFields;

    /** Самая ранняя допустимая дата рождения. */
    private const string BIRTH_DATE_AFTER = '1900-01-01';

    /** Телефон: цифры, пробелы, скобки, дефисы и необязательный «+» в начале. */
    private const string PHONE_PATTERN = '/^\+?[0-9 ()-]+$/';

    /**
     * Правила проверки анкеты; список соцсетей зависит от настроек сайта.
     *
     * @return array<string, mixed>
     */
    public function rules(ProfileService $profiles): array
    {
        $fields = collect(ProfileField::cases())->map(static fn (ProfileField $field): string => $field->value);

        return [
            'last_name' => $this->personNameRules(),
            'first_name' => $this->personNameRules(),
            'middle_name' => $this->personNameRules(),
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'after:' . self::BIRTH_DATE_AFTER, 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'about' => ['nullable', 'string', 'max:' . User::ABOUT_LENGTH],
            'contacts' => ['array:phone,email'],
            'contacts.phone' => [
                'nullable',
                'string',
                'min:' . User::PHONE_MIN_LENGTH,
                'max:' . User::PHONE_LENGTH,
                'regex:' . self::PHONE_PATTERN,
            ],
            'contacts.email' => ['nullable', 'email', 'max:' . User::EMAIL_LENGTH],
            'socials' => ['array:' . $profiles->networks()->implode(',')],
            'socials.*' => ['nullable', 'url:https', 'max:' . User::SOCIAL_URL_LENGTH],
            'visibility' => ['array:' . $fields->implode(',')],
            'visibility.*' => ['boolean'],
        ];
    }

    /**
     * Подписи полей в сообщениях об ошибках.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('profile.fields');
    }

    /**
     * Анкета из проверенных данных.
     */
    public function profileData(): ProfileData
    {
        $birthDate = $this->validated('birth_date');
        $gender = $this->validated('gender');

        return new ProfileData(
            name: new PersonName(
                $this->validated('last_name'),
                $this->validated('first_name'),
                $this->validated('middle_name'),
            ),
            birthDate: $birthDate === null ? null : new DateTimeImmutable($birthDate),
            gender: $gender === null ? null : Gender::from($gender),
            about: $this->validated('about'),
            contacts: new ContactsData(
                phone: $this->validated('contacts.phone'),
                email: $this->validated('contacts.email'),
            ),
            socials: collect($this->validated('socials')),
            visibility: collect($this->validated('visibility'))
                ->map(static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN)),
        );
    }
}
