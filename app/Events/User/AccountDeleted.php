<?php

declare(strict_types=1);

namespace App\Events\User;

use App\Entities\User;
use App\Enums\Legal\ConsentType;
use Illuminate\Support\Collection;

/**
 * Пользователь удалил свою учётную запись. Слушатели работают сразу, в том же запросе.
 */
final readonly class AccountDeleted
{
    /**
     * @param User                         $user            удалённый пользователь
     * @param Collection<int, ConsentType> $revokedConsents согласия, которые он отозвал вместе с удалением
     * @param string|null                  $ip              адрес, с которого пришёл запрос
     * @param string|null                  $userAgent       браузер
     */
    public function __construct(
        public User $user,
        public Collection $revokedConsents,
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }
}
