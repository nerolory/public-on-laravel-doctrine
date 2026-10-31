<?php

declare(strict_types=1);

namespace App\DTO\Admin;

use Illuminate\Support\Collection;

/**
 * Строка списка пользователей в админке с ролями и действиями.
 */
final readonly class AdminUserRowData
{
    /**
     * @param int                                  $id           идентификатор пользователя
     * @param string                               $name         имя для отображения
     * @param string                               $email        адрес почты
     * @param bool                                 $banned       заблокирован ли пользователь
     * @param bool                                 $manageable   может ли текущий администратор менять роли и карточку
     * @param string                               $rolesLabel   роли через запятую, начиная с роли пользователя
     * @param string                               $editUrl      адрес формы правки
     * @param string                               $banRoute     имя маршрута блокировки или разблокировки
     * @param string                               $banIcon      значок кнопки блокировки
     * @param string                               $banTitle     подсказка кнопки блокировки
     * @param string                               $banAriaLabel подпись кнопки блокировки для чтения с экрана
     * @param Collection<int, AdminRoleToggleData> $roleToggles  переключатели ролей
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public bool $banned,
        public bool $manageable,
        public string $rolesLabel,
        public string $editUrl,
        public string $banRoute,
        public string $banIcon,
        public string $banTitle,
        public string $banAriaLabel,
        public Collection $roleToggles,
    ) {
    }
}
