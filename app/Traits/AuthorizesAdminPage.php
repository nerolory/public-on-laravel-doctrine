<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\User\Ability;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Проверка права администратора при открытии страницы MoonShine. Страница хранит Gate в свойстве $gate
 * и объявляет нужное право в ability().
 */
trait AuthorizesAdminPage
{
    /**
     * Проверка права перед загрузкой страницы.
     *
     * @throws AuthorizationException у администратора нет права: ответ 403
     */
    protected function onLoad(): void
    {
        parent::onLoad();
        $this->authorizeAbility($this->ability());
    }

    /**
     * Право, без которого страница не открывается.
     */
    abstract protected function ability(): Ability;

    /**
     * Проверка способности текущего администратора.
     *
     * @throws AuthorizationException у администратора нет права: ответ 403
     */
    protected function authorizeAbility(Ability $ability): void
    {
        $this->gate->authorize($ability->value);
    }
}
