<?php

declare(strict_types=1);

namespace App\Enums\User;

/**
 * Поля профиля, видимость которых пользователь выбирает сам. По умолчанию все скрыты.
 */
enum ProfileField: string
{
    /** ФИО. */
    case Name = 'name';

    /** Дата рождения. */
    case BirthDate = 'birth_date';

    /** Пол. */
    case Gender = 'gender';

    /** Пояснение о себе. */
    case About = 'about';

    /** Контакты. */
    case Contacts = 'contacts';

    /** Ссылки на профили в соцсетях. */
    case Socials = 'socials';
}
