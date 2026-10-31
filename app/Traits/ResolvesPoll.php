<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\Poll;

/**
 * Запрос к опросу из адреса: опрос подставляется маршрутом.
 */
trait ResolvesPoll
{
    /**
     * Опрос из адреса.
     */
    public function poll(): Poll
    {
        return $this->route('poll');
    }
}
