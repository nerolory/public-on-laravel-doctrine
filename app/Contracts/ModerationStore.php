<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\Moderation\ModerationAction;

/**
 * Хранилище материалов, которые модерируются пакетом: удаляет их и записывает изменения состояния.
 * Действие {@see ModerationAction} выбирает, какой из этих методов вызвать.
 */
interface ModerationStore
{
    /**
     * Удаляет материалы вместе с их данными.
     *
     * @param iterable<Moderatable> $subjects
     */
    public function delete(iterable $subjects): void;

    /**
     * Записывает изменения состояния загруженных материалов.
     */
    public function flush(): void;
}
