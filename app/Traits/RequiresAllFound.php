<?php

declare(strict_types=1);

namespace App\Traits;

use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Support\Collection;

/**
 * Проверка пакетного действия: каждый запрошенный идентификатор нашёлся, иначе ответ 404.
 */
trait RequiresAllFound
{
    /**
     * Все запрошенные записи найдены.
     *
     * @param Collection<int, int|string> $requested идентификаторы из запроса
     * @param Collection<int, int|string> $found     идентификаторы найденных записей
     * @param class-string                $entity    класс сущности для сообщения об ошибке
     * @param string                      $field     поле идентификатора в сообщении
     *
     * @throws EntityNotFoundException хотя бы одной записи нет
     */
    private function requireAllFound(Collection $requested, Collection $found, string $entity, string $field): void
    {
        $missing = $requested->unique()->diff($found);

        if ($missing->isNotEmpty()) {
            throw EntityNotFoundException::fromClassNameAndIdentifier($entity, [$field => $missing->implode(',')]);
        }
    }
}
