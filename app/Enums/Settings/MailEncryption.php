<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Traits\ProvidesEnumValues;

/**
 * Шифрование соединения с SMTP-сервером из настроек почты.
 */
enum MailEncryption: string
{
    use ProvidesEnumValues;
    /** STARTTLS: соединение начинается открытым и обязательно переходит на TLS. */
    case Tls = 'tls';

    /** Отдельная защищённая схема smtps, обычно порт 465. */
    case Ssl = 'ssl';

    /** Без шифрования. */
    case None = 'none';

    /**
     * Схема транспорта Symfony Mailer.
     */
    public function scheme(): string
    {
        return $this === self::Ssl ? 'smtps' : 'smtp';
    }

    /**
     * Переходить ли на TLS, если сервер его предлагает.
     */
    public function autoTls(): bool
    {
        return $this !== self::None;
    }

    /**
     * Требовать ли переход на TLS.
     */
    public function requiresTls(): bool
    {
        return $this === self::Tls;
    }
}
