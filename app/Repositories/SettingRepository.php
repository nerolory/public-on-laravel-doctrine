<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Settings\SettingValueData;
use App\Entities\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Сохранённые значения настроек сайта.
 */
final class SettingRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Все сохранённые значения.
     *
     * @return Collection<int, SettingValueData>
     */
    public function all(): Collection
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s.key, s.value')
            ->from(Setting::class, 's')
            ->getQuery()
            ->getArrayResult();

        return collect($rows)
            ->map(static fn (array $row): SettingValueData => new SettingValueData($row['key'], $row['value']))
            ->values();
    }

    /**
     * Сохраняет значения: существующие настройки меняются, новые создаются.
     *
     * @param Collection<int, SettingValueData> $values значения
     */
    public function save(Collection $values): void
    {
        if ($values->isEmpty()) {
            return;
        }

        $existing = $this->existingByKey($values);

        foreach ($values as $value) {
            $setting = $existing->get($value->key);

            if ($setting === null) {
                $this->entityManager->persist(Setting::create($value->key, $value->value));

                continue;
            }

            $setting->change($value->value);
        }

        $this->entityManager->flush();
    }

    /**
     * Добавляет значения, которых ещё нет в базе; сохранённые значения не меняются.
     *
     * @param Collection<int, SettingValueData> $values значения по умолчанию
     */
    public function insertMissing(Collection $values): void
    {
        $existing = $this->existingByKey($values);

        foreach ($values as $value) {
            if (! $existing->has($value->key)) {
                $this->entityManager->persist(Setting::create($value->key, $value->value));
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Заполняет сохранённые пустые значения: обязательная настройка без значения получает значение по умолчанию.
     *
     * @param Collection<int, SettingValueData> $values значения по умолчанию обязательных настроек
     */
    public function fillEmpty(Collection $values): void
    {
        $existing = $this->existingByKey($values);

        foreach ($values as $value) {
            $setting = $existing->get($value->key);

            if ($setting !== null && in_array($setting->getValue(), [null, ''], strict: true)) {
                $setting->change($value->value);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Настройки из базы по ключам значений.
     *
     * @param Collection<int, SettingValueData> $values
     *
     * @return Collection<string, Setting> по ключу
     */
    private function existingByKey(Collection $values): Collection
    {
        $keys = $values->map(static fn (SettingValueData $value): string => $value->key)->all();
        $found = $this->entityManager->getRepository(Setting::class)->findBy(['key' => $keys]);

        return collect($found)->keyBy(static fn (Setting $setting): string => $setting->getKey());
    }
}
