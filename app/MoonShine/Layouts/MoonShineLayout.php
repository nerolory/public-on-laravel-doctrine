<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\Entities\User;
use App\MoonShine\Pages\Album\AlbumIndexPage;
use App\MoonShine\Pages\Analytics\AnalyticsPage;
use App\MoonShine\Pages\Article\ArticleIndexPage;
use App\MoonShine\Pages\ArticleSection\ArticleSectionIndexPage;
use App\MoonShine\Pages\Complaint\ComplaintIndexPage;
use App\MoonShine\Pages\Dashboard;
use App\MoonShine\Pages\Legal\LegalDocumentIndexPage;
use App\MoonShine\Pages\Localization\LanguageIndexPage;
use App\MoonShine\Pages\Reaction\ConflictRatingPage;
use App\MoonShine\Pages\Settings\SiteSettingsPage;
use App\MoonShine\Pages\Statistics\StatisticsPage;
use App\MoonShine\Pages\User\RoleIndexPage;
use App\MoonShine\Pages\User\UserIndexPage;
use App\Services\User\PermissionService;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Vite;
use MoonShine\AssetManager\Raw;
use MoonShine\ColorManager\Palettes\NeutralPalette;
use MoonShine\Contracts\AssetManager\AssetElementContract;
use MoonShine\Contracts\AssetManager\AssetManagerContract;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\MenuManager\MenuAutoloaderContract;
use MoonShine\Contracts\MenuManager\MenuManagerContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuItem;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Оформление админки: меню по правам текущего администратора и кнопка выхода.
 */
final class MoonShineLayout extends AppLayout
{
    /**
     * Нейтральная палитра MoonShine.
     *
     * @var class-string<PaletteContract>|null
     */
    protected ?string $palette = NeutralPalette::class;

    /**
     * @param CoreContract           $core           ядро MoonShine
     * @param AssetManagerContract   $assetManager   стили и скрипты
     * @param ColorManagerContract   $colorManager   цвета
     * @param MenuManagerContract    $menuManager    меню
     * @param MenuAutoloaderContract $menuAutoloader автозагрузка меню
     * @param PermissionService      $permissions    роли
     * @param AuthFactory            $auth           текущий администратор
     * @param Vite                   $vite           сборка ассетов админки
     * @param ViewFactory            $views          шаблоны
     */
    public function __construct(
        CoreContract $core,
        AssetManagerContract $assetManager,
        ColorManagerContract $colorManager,
        MenuManagerContract $menuManager,
        MenuAutoloaderContract $menuAutoloader,
        private readonly PermissionService $permissions,
        private readonly AuthFactory $auth,
        private readonly Vite $vite,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core, $assetManager, $colorManager, $menuManager, $menuAutoloader);
    }

    /**
     * Пункты меню; разделы управления пользователями и настройками видны только при соответствующих правах.
     *
     * @return list<MenuItem>
     */
    protected function menu(): array
    {
        $manageUsers = $this->can(fn (User $user): bool => $this->permissions->canManageUsers($user));
        $manageSettings = $this->can(fn (User $user): bool => $this->permissions->canManageSettings($user));

        return [
            MenuItem::make(Dashboard::class, __('admin.menu.dashboard'), 'chart-bar'),
            MenuItem::make(ArticleIndexPage::class, __('admin.menu.articles'), 'document-text'),
            MenuItem::make(ArticleSectionIndexPage::class, __('admin.menu.sections'), 'folder'),
            MenuItem::make(AlbumIndexPage::class, __('admin.menu.albums'), 'photo'),
            MenuItem::make(ComplaintIndexPage::class, __('admin.menu.complaints'), 'flag'),
            MenuItem::make(StatisticsPage::class, __('admin.menu.statistics'), 'chart-pie')->canSee($manageUsers),
            MenuItem::make(AnalyticsPage::class, __('admin.menu.analytics'), 'presentation-chart-line')
                ->canSee($manageSettings),
            MenuItem::make(ConflictRatingPage::class, __('admin.menu.conflicts'), 'fire')->canSee($manageUsers),
            MenuItem::make(UserIndexPage::class, __('admin.menu.users'), 'users')->canSee($manageUsers),
            MenuItem::make(RoleIndexPage::class, __('admin.menu.roles'), 'shield-check')->canSee($manageUsers),
            MenuItem::make(LegalDocumentIndexPage::class, __('legal.admin.menu'), 'scale')->canSee($manageSettings),
            MenuItem::make(LanguageIndexPage::class, __('languages.menu'), 'language')->canSee($manageSettings),
            MenuItem::make(SiteSettingsPage::class, __('settings.menu'), 'cog-6-tooth')->canSee($manageSettings),
        ];
    }

    /**
     * Стили и скрипты MoonShine и общие ассеты страниц админки из сборки frontend/editor.
     *
     * @return list<AssetElementContract>
     */
    protected function assets(): array
    {
        $editor = (string) ($this->vite)(['admin.ts']);

        return [...parent::assets(), Raw::make($editor)];
    }

    /**
     * Кнопка выхода в боковой панели.
     *
     * @return list<FlexibleRender>
     */
    protected function sidebarSlot(): array
    {
        return [FlexibleRender::make($this->views->make('admin.logout'))];
    }

    /**
     * Условие видимости пункта меню для текущего администратора.
     *
     * @param Closure(User): bool $check
     *
     * @return Closure(): bool
     */
    private function can(Closure $check): Closure
    {
        return function () use ($check): bool {
            $user = $this->auth->guard()->user();

            return $user instanceof User && $check($user);
        };
    }
}
