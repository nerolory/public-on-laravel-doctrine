<?php

declare(strict_types=1);

namespace App\DTO\Settings;

/**
 * Итог проверочного письма: отправлено или нет и текст для администратора.
 */
final readonly class MailTestData
{
    /**
     * @param bool   $failed  письмо не отправлено из-за ошибки транспорта
     * @param string $message текст результата
     */
    public function __construct(
        public bool $failed,
        public string $message,
    ) {
    }
}
