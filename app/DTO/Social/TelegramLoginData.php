<?php

declare(strict_types=1);

namespace App\DTO\Social;

use Illuminate\Support\Collection;

/**
 * Данные входа через Telegram из фрагмента #tgAuthResult.
 */
final readonly class TelegramLoginData
{
    /**
     * @param string                     $id           идентификатор пользователя Telegram
     * @param string|null                $firstName    имя
     * @param string|null                $lastName     фамилия
     * @param int                        $authDate     время входа, Unix-время
     * @param string                     $hash         подпись данных ботом
     * @param Collection<string, string> $signedFields поля для подписи, кроме hash
     */
    public function __construct(
        public string $id,
        public ?string $firstName,
        public ?string $lastName,
        public int $authDate,
        public string $hash,
        public Collection $signedFields,
    ) {
    }
}
