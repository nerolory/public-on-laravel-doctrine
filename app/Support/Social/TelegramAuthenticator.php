<?php

declare(strict_types=1);

namespace App\Support\Social;

use App\DTO\Social\SocialIdentityData;
use App\DTO\Social\TelegramLoginData;
use App\Enums\Social\SocialProvider;
use DateTimeImmutable;

/**
 * Проверка данных входа через Telegram по официальной схеме:
 * HMAC-SHA256 строки проверки с ключом SHA256(токен бота) должен совпасть с полем hash.
 * https://core.telegram.org/widgets/login#checking-authorization
 */
final class TelegramAuthenticator
{
    /** Данные входа старше суток не принимаются. */
    private const int MAX_AGE_SECONDS = 86400;

    /**
     * Подписаны ли данные ботом сайта и не устарели ли они.
     */
    public function isValid(TelegramLoginData $login, string $botToken): bool
    {
        // Возраст данных — секунды с момента входа в Telegram до проверки на сервере.
        $age = (new DateTimeImmutable())->getTimestamp() - $login->authDate;

        // Проверяются три условия: токен бота задан, возраст данных в пределах суток и подпись совпала.
        return $botToken !== ''
            && $age <= self::MAX_AGE_SECONDS
            && hash_equals(self::signature($login, $botToken), $login->hash);
    }

    /**
     * Пользователь Telegram по проверенным данным. Telegram не передаёт email: его спросят при регистрации.
     */
    public function identity(TelegramLoginData $login): SocialIdentityData
    {
        $name = trim($login->firstName . ' ' . $login->lastName);

        return new SocialIdentityData(
            provider: SocialProvider::Telegram->value,
            id: $login->id,
            email: null,
            name: $name === '' ? null : $name,
        );
    }

    /**
     * Подпись: поля без hash в алфавитном порядке, строки «ключ=значение» через перевод строки.
     */
    private static function signature(TelegramLoginData $login, string $botToken): string
    {
        $checkString = $login->signedFields
            ->sortKeys()
            ->map(static fn (string $value, string $key): string => $key . '=' . $value)
            ->implode("\n");

        return hash_hmac('sha256', $checkString, hash('sha256', $botToken, binary: true));
    }
}
