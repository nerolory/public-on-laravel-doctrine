<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Переключатель роли пользователя: выдача или снятие роли отправкой формы.
 */
final readonly class AdminRoleToggleData
{
    /**
     * @param string $code        код роли
     * @param string $label       название роли
     * @param bool   $checked     есть ли роль у пользователя
     * @param string $operation   операция, которую отправит переключатель
     * @param string $submitLabel подпись кнопки для варианта без скриптов
     */
    public function __construct(
        public string $code,
        public string $label,
        public bool $checked,
        public string $operation,
        public string $submitLabel,
    ) {
    }
}
