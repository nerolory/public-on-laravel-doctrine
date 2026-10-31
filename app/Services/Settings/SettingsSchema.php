<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\DTO\Settings\SettingValueData;
use App\Enums\Settings\SettingGroup;
use App\Support\Settings\SettingDefinition;
use App\Traits\DescribesSettingGroups;
use Illuminate\Support\Collection;

/**
 * Реестр всех настроек сайта по группам. Новые группы добавляются в {@see DescribesSettingGroups}: форма в админке,
 * проверка и значения по умолчанию строятся из этого описания.
 */
final class SettingsSchema
{
    use DescribesSettingGroups;

    /** Название сайта до первой правки; оно же имя отправителя писем по умолчанию. */
    public const string SITE_NAME_DEFAULT = 'Слова и смыслы';

    /** @var Collection<string, Collection<int, SettingDefinition>>|null по значению SettingGroup */
    private ?Collection $groups = null;

    /**
     * @param string $defaultFromAddress адрес отправителя писем до первой правки: MAIL_FROM_ADDRESS из окружения
     */
    public function __construct(
        private readonly string $defaultFromAddress,
    ) {
    }

    /**
     * Все группы с описаниями настроек по значению SettingGroup; порядок групп — порядок вкладок в админке.
     *
     * @return Collection<string, Collection<int, SettingDefinition>>
     */
    public function groups(): Collection
    {
        return $this->groups ??= collect(SettingGroup::cases())->mapWithKeys(
            fn (SettingGroup $group): array => [$group->value => collect($this->definitionsOf($group))],
        );
    }

    /**
     * Настройки группы.
     *
     * @return Collection<int, SettingDefinition>
     */
    public function group(SettingGroup $group): Collection
    {
        return $this->groups()->get($group->value);
    }

    /**
     * Значения по умолчанию всех настроек: их записывает SettingsSeeder.
     *
     * @return Collection<int, SettingValueData>
     */
    public function defaults(): Collection
    {
        return self::defaultValues($this->groups()->flatten(depth: 1));
    }

    /**
     * Значения по умолчанию для обязательных настроек: ими заполняются пустые значения в базе.
     *
     * @return Collection<int, SettingValueData>
     */
    public function requiredDefaults(): Collection
    {
        $required = $this->groups()->flatten(depth: 1)
            ->filter(static fn (SettingDefinition $definition): bool => $definition->required);

        return self::defaultValues($required);
    }

    /**
     * Значения по умолчанию для описаний настроек.
     *
     * @param Collection<int, SettingDefinition> $definitions
     *
     * @return Collection<int, SettingValueData>
     */
    private static function defaultValues(Collection $definitions): Collection
    {
        return $definitions
            ->map(static fn (SettingDefinition $definition): SettingValueData => new SettingValueData(
                $definition->key->value,
                $definition->default,
            ))
            ->values();
    }

    /**
     * Описания настроек группы.
     *
     * @return list<SettingDefinition>
     */
    private function definitionsOf(SettingGroup $group): array
    {
        return match ($group) {
            SettingGroup::General => $this->general(),
            SettingGroup::Content => $this->content(),
            SettingGroup::Pagination => $this->pagination(),
            SettingGroup::Statistics => $this->statistics(),
            SettingGroup::Seo => $this->seo(),
            SettingGroup::Share => $this->share(),
            SettingGroup::Metrics => $this->metrics(),
            SettingGroup::Legal => $this->legal(),
            SettingGroup::Localization => $this->localization(),
            SettingGroup::Profile => $this->profile(),
            SettingGroup::Social => $this->social(),
            SettingGroup::Mail => $this->mail($this->defaultFromAddress),
            SettingGroup::Storage => $this->storage(),
        };
    }
}
