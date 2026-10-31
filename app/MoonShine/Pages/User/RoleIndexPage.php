<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\User;

use App\Entities\Role;
use App\Enums\User\Ability;
use App\Traits\AuthorizesAdminPage;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\Factory as ViewFactory;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Справка по ролям и их правам.
 */
final class RoleIndexPage extends Page
{
    use AuthorizesAdminPage;

    /** Адрес страницы в админке: /admin/page/roles. */
    protected ?string $alias = 'roles';

    /**
     * @param CoreContract $core  ядро MoonShine
     * @param Gate         $gate  права доступа
     * @param ViewFactory  $views шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly Gate $gate,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.roles.title');
    }

    /**
     * Право на открытие страницы: управление пользователями.
     */
    protected function ability(): Ability
    {
        return Ability::ManageUsers;
    }

    /**
     * Описание всех ролей.
     *
     * @return iterable<FlexibleRender>
     */
    protected function components(): iterable
    {
        return [FlexibleRender::make($this->views->make('admin.roles', ['roles' => collect(Role::CODES)]))];
    }
}
