<?php

declare(strict_types=1);

namespace App\DTO\User;

use App\Entities\PersonName;
use App\Enums\User\Gender;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Анкета профиля: форма личного кабинета и данные владельца аккаунта.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ProfileData implements Arrayable
{
    /**
     * @param PersonName                 $name       фамилия, имя и отчество
     * @param DateTimeImmutable|null     $birthDate  дата рождения
     * @param Gender|null                $gender     пол
     * @param string|null                $about      текст «О себе»
     * @param ContactsData               $contacts   телефон и почта для связи
     * @param Collection<string, string> $socials    ссылки на соцсети по ключу сети
     * @param Collection<string, bool>   $visibility видимость полей по значению ProfileField
     */
    public function __construct(
        public PersonName $name,
        public ?DateTimeImmutable $birthDate,
        public ?Gender $gender,
        public ?string $about,
        public ContactsData $contacts,
        public Collection $socials,
        public Collection $visibility,
    ) {
    }

    /**
     * Поля анкеты без видимости. Контакты и ссылки уходят объектами, даже пустые.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'last_name' => $this->name->lastName(),
            'first_name' => $this->name->firstName(),
            'middle_name' => $this->name->middleName(),
            'birth_date' => $this->birthDate?->format('Y-m-d'),
            'gender' => $this->gender?->value,
            'about' => $this->about,
            'contacts' => (object) $this->contacts->toArray(),
            'socials' => (object) $this->socials->all(),
        ];
    }
}
