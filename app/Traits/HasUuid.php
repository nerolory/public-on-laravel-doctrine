<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Сущность с публичным UUID: им адресуются альбомы, графики, картинки и опросы.
 */
trait HasUuid
{
    /** Длина UUID в строковом виде с дефисами: совпадает с длиной колонки uuid. */
    public const int UUID_LENGTH = 36;
}
