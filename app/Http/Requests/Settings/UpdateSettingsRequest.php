<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\DTO\Settings\SettingsGroupData;
use App\DTO\Settings\SettingValueData;
use App\Enums\Settings\SettingGroup;
use App\Services\Settings\SettingsSchema;
use App\Support\Settings\SettingDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Сохранение группы настроек из формы в админке; группа берётся из адреса, право на настройки проверяет
 * middleware маршрута.
 */
final class UpdateSettingsRequest extends FormRequest
{
    /**
     * @param SettingsSchema $schema описания настроек всех групп
     */
    public function __construct(
        private readonly SettingsSchema $schema,
    ) {
    }

    /**
     * Правила полей группы по описаниям настроек.
     *
     * @return array<string, list<string|object>>
     */
    public function rules(): array
    {
        return $this->definitions()
            ->mapWithKeys(static fn (SettingDefinition $definition): array => [
                $definition->field() => $definition->validationRules(),
            ])
            ->all();
    }

    /**
     * Названия полей в сообщениях об ошибках.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->definitions()
            ->mapWithKeys(static fn (SettingDefinition $definition): array => [
                $definition->field() => __('settings.fields.' . $definition->key->value),
            ])
            ->all();
    }

    /**
     * Значения группы из проверенной формы; поля, не пришедшие в форме, получают null.
     */
    public function toData(): SettingsGroupData
    {
        $validated = $this->validated();

        $values = $this->definitions()->map(
            static fn (SettingDefinition $definition): SettingValueData => new SettingValueData(
                $definition->key->value,
                Arr::get($validated, $definition->field()),
            ),
        );

        return new SettingsGroupData(group: $this->group(), values: $values->values());
    }

    /**
     * Группа настроек из адреса; маршрут принимает только значения SettingGroup (решение 25: привязка enum
     * в сигнатуре контроллера недоступна из-за SubstituteBindings Laravel Doctrine).
     */
    public function group(): SettingGroup
    {
        return SettingGroup::from((string) $this->route('group'));
    }

    /**
     * Описания настроек группы из адреса.
     *
     * @return Collection<int, SettingDefinition>
     */
    private function definitions(): Collection
    {
        return $this->schema->group($this->group());
    }
}
