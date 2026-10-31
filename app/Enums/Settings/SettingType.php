<?php

declare(strict_types=1);

namespace App\Enums\Settings;

/**
 * Тип настройки: задаёт поле формы в админке, проверку и приведение значения.
 */
enum SettingType: string
{
    case String = 'string';

    case Text = 'text';

    case Boolean = 'boolean';

    case Integer = 'integer';

    case Select = 'select';

    case Secret = 'secret';

    case Email = 'email';

    case Url = 'url';

    /**
     * Приводит значение из формы к типу настройки.
     */
    public function cast(mixed $value): mixed
    {
        return match ($this) {
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Integer => $value === null || $value === '' ? null : (int) $value,
            default => $value === null ? null : trim((string) $value),
        };
    }
}
