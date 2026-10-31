<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Entities\User;
use App\Enums\Settings\SettingKey;
use App\Enums\Settings\SettingType;

/**
 * Описание одной настройки: тип, значение по умолчанию и правила проверки.
 */
final readonly class SettingDefinition
{
    /** Длина строковых настроек и адресов: ключи API, хосты, ссылки. */
    private const int STRING_LENGTH = 500;

    /** Длина многострочных настроек: дополнительные правила robots.txt и описания. */
    private const int TEXT_LENGTH = 20000;

    /**
     * @param SettingKey          $key      ключ настройки, например mail.host
     * @param SettingType         $type     тип значения и поля формы
     * @param mixed               $default  значение, пока настройку не сохранили
     * @param list<string|object> $rules    дополнительные правила проверки
     * @param list<string>        $options  значения для выпадающего списка;
     *                                      подписи берутся из lang/{locale}/settings.php
     * @param bool                $required значение обязательно: пустое поле формы не сохраняется
     * @param bool                $hasHint  есть подсказка settings.hints.{key} в lang/{locale}/settings.php
     */
    public function __construct(
        public SettingKey $key,
        public SettingType $type,
        public mixed $default = null,
        public array $rules = [],
        public array $options = [],
        public bool $required = false,
        public bool $hasHint = false,
    ) {
    }

    /**
     * Имя поля формы: точки в ключе заменяются, чтобы Laravel не превращал их во вложенные массивы.
     */
    public function field(): string
    {
        return str_replace('.', '__', $this->key->value);
    }

    /**
     * Правила проверки значения из формы: по типу и дополнительные.
     *
     * @return list<string|object>
     */
    public function validationRules(): array
    {
        $base = match ($this->type) {
            SettingType::Boolean => ['boolean'],
            SettingType::Integer => ['integer'],
            SettingType::Email => ['email', 'max:' . User::EMAIL_LENGTH],
            SettingType::Url => ['url', 'max:' . self::STRING_LENGTH],
            SettingType::Text => ['string', 'max:' . self::TEXT_LENGTH],
            SettingType::Select => ['string', 'in:' . implode(',', $this->options)],
            SettingType::String, SettingType::Secret => ['string', 'max:' . self::STRING_LENGTH],
        };

        $presence = $this->required ? 'required' : 'nullable';

        return [$presence, ...$base, ...$this->rules];
    }
}
