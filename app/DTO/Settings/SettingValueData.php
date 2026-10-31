<?php

declare(strict_types=1);

namespace App\DTO\Settings;

/**
 * Значение одной настройки: ключ вида group.name и значение, приведённое к типу настройки.
 */
final readonly class SettingValueData
{
    /**
     * @param string $key   ключ настройки, например mail.host
     * @param mixed  $value значение; null — настройка не заполнена
     */
    public function __construct(
        public string $key,
        public mixed $value,
    ) {
    }
}
