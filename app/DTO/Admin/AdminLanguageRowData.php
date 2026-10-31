<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Строка списка языков сайта в админке.
 */
final readonly class AdminLanguageRowData
{
    /**
     * @param string $code        код языка
     * @param string $name        название языка
     * @param string $file        файл переводов
     * @param string $statusLabel подпись статуса: по умолчанию, включён или выключен
     * @param bool   $deletable   можно ли удалить язык
     * @param string $deleteTitle подсказка кнопки удаления
     * @param string $editUrl     адрес формы правки
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $file,
        public string $statusLabel,
        public bool $deletable,
        public string $deleteTitle,
        public string $editUrl,
    ) {
    }
}
