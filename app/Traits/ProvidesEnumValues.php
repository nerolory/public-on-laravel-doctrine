<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Collection;

/**
 * Значения backed-enum: списки выбора в настройках и ограничения параметров маршрутов.
 */
trait ProvidesEnumValues
{
    /**
     * Значения всех случаев в порядке объявления.
     *
     * @return Collection<int, string>
     */
    public static function values(): Collection
    {
        return collect(self::cases())->map(static fn (self $case): string => $case->value);
    }
}
