<?php

declare(strict_types=1);

namespace App\Enums\User;

/**
 * Пол в анкете профиля.
 */
enum Gender: string
{
    /** Мужской пол. */
    case Male = 'male';

    /** Женский пол. */
    case Female = 'female';
}
