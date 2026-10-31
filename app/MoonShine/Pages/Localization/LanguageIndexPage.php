<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Localization;

use App\DTO\Admin\AdminLanguageRowData;
use App\Entities\Language;
use App\Enums\Settings\SettingGroup;
use App\Enums\User\Ability;
use App\MoonShine\Pages\Settings\SiteSettingsPage;
use App\Services\Localization\LanguageService;
use App\Support\Settings\PageSizes;
use App\Traits\AuthorizesAdminPage;
use App\Traits\PaginatesList;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\Factory as ViewFactory;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;

/**
 * Список языков сайта.
 */
final class LanguageIndexPage extends Page
{
    use AuthorizesAdminPage;
    use PaginatesList;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Адрес страницы в админке: /admin/page/languages. */
    protected ?string $alias = 'languages';

    /**
     * @param CoreContract    $core      ядро MoonShine
     * @param LanguageService $languages языки
     * @param Gate            $gate      права доступа
     * @param PageSizes       $pageSizes размер страницы таблицы
     * @param ViewFactory     $views     шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly LanguageService $languages,
        private readonly Gate $gate,
        private readonly PageSizes $pageSizes,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('languages.title');
    }

    /**
     * Хлебные крошки: админка → языки.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs($this->getTitle());
    }

    /**
     * Право на открытие страницы: управление настройками.
     */
    protected function ability(): Ability
    {
        return Ability::ManageSettings;
    }

    /**
     * Таблица языков с действиями по страницам.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $page = $this->pageNumber();
        $size = $this->pageSizes->admin();
        $languages = $this->languages->page($page, $size);

        return $this->listComponents(
            $this->views->make('admin.languages', [
                'languages' => $languages->items->map($this->row(...)),
                'createUrl' => $this->adminPageUrl(LanguageFormPage::class),
                'localizationSettingsUrl' => $this->adminPageUrl(
                    SiteSettingsPage::class,
                    ['group' => SettingGroup::Localization->value],
                ),
            ]),
            $languages,
            $page,
            $size,
        );
    }

    /**
     * Строка таблицы языков.
     */
    private function row(Language $language): AdminLanguageRowData
    {
        $isDefault = $language->isDefault();

        return new AdminLanguageRowData(
            code: $language->getCode(),
            name: $language->getName(),
            file: $language->getFile(),
            statusLabel: $this->statusLabel($language),
            deletable: ! $isDefault,
            deleteTitle: __($isDefault ? 'languages.cannot_delete_default' : 'languages.delete'),
            editUrl: $this->adminPageUrl(LanguageFormPage::class, ['code' => $language->getCode()]),
        );
    }

    /**
     * Подпись статуса языка: по умолчанию, включён или выключен.
     */
    private function statusLabel(Language $language): string
    {
        if ($language->isDefault()) {
            return __('languages.status.default');
        }

        return __($language->isEnabled() ? 'languages.status.enabled' : 'languages.status.disabled');
    }
}
