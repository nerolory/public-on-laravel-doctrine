<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Legal;

use App\DTO\Admin\AdminLegalDocumentRowData;
use App\DTO\Legal\LegalDocumentRowData;
use App\Enums\Settings\SettingGroup;
use App\Enums\User\Ability;
use App\MoonShine\Pages\Settings\SiteSettingsPage;
use App\Services\Legal\LegalDocumentService;
use App\Support\Settings\PageSizes;
use App\Traits\AuthorizesAdminPage;
use App\Traits\LabelsLegalRegion;
use App\Traits\PaginatesList;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\Factory as ViewFactory;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;

/**
 * Правовые документы: последняя версия каждого вида по регионам.
 */
final class LegalDocumentIndexPage extends Page
{
    use AuthorizesAdminPage;
    use LabelsLegalRegion;
    use PaginatesList;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Формат даты версии документа в таблице. */
    private const string DATE_FORMAT = 'd.m.Y';

    /** Адрес страницы в админке: /admin/page/legal-documents. */
    protected ?string $alias = 'legal-documents';

    /**
     * @param CoreContract         $core      ядро MoonShine
     * @param LegalDocumentService $documents правовые документы
     * @param Gate                 $gate      права доступа
     * @param PageSizes            $pageSizes размер страницы таблицы
     * @param ViewFactory          $views     шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly LegalDocumentService $documents,
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
        return __('legal.admin.title');
    }

    /**
     * Хлебные крошки: админка → документы.
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
     * Таблица «вид документа × регион» по страницам; пустой регион — документ для всех регионов.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $page = $this->pageNumber();
        $size = $this->pageSizes->admin();
        $rows = $this->documents->overview($page, $size);

        return $this->listComponents(
            $this->views->make('admin.legal-documents', [
                'rows' => $rows->items->map($this->row(...)),
                'currentRegionLabel' => $this->legalRegionLabel($this->documents->currentRegion()),
                'regionSettingsUrl' => $this->adminPageUrl(
                    SiteSettingsPage::class,
                    ['group' => SettingGroup::Legal->value],
                ),
            ]),
            $rows,
            $page,
            $size,
        );
    }

    /**
     * Строка таблицы: вид, регион, текущая версия и адрес новой версии.
     */
    private function row(LegalDocumentRowData $row): AdminLegalDocumentRowData
    {
        $region = $row->region === null ? '' : $row->region;
        $document = $row->document;

        return new AdminLegalDocumentRowData(
            typeLabel: __('legal.types.' . $row->type->value),
            regionLabel: $this->legalRegionLabel($region),
            versionLabel: $document === null
                ? __('legal.admin.missing')
                : __('legal.admin.version', [
                    'version' => $document->getVersion(),
                    'date' => $document->getCreatedAt()->format(self::DATE_FORMAT),
                ]),
            editUrl: $this->adminPageUrl(
                LegalDocumentFormPage::class,
                ['type' => $row->type->value, 'region' => $region],
            ),
        );
    }
}
