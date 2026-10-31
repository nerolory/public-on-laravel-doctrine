<?php

declare(strict_types=1);

namespace App\Traits;

use LogicException;

/**
 * Идентификатор сохранённой сущности: база выдаёт его при первом сохранении, у загруженной записи он есть всегда.
 */
trait HasPersistedId
{
    /**
     * Идентификатор из базы; null до первого сохранения.
     */
    abstract public function getId(): ?int;

    /**
     * Идентификатор сохранённой записи.
     *
     * @throws LogicException сущность ещё не сохранена
     */
    public function persistedId(): int
    {
        return $this->getId() ?? throw new LogicException(static::class . ' is not persisted yet.');
    }
}
