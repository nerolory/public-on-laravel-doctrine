<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Legal;

use App\Entities\LegalDocument;
use App\Enums\Legal\ConsentType;
use App\Enums\User\Ability;
use App\Services\Legal\LegalDocumentService;
use App\Traits\AuthorizesAdminPage;
use App\Traits\BuildsAdminForms;
use App\Traits\LabelsLegalRegion;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * Новая версия документа вида ?type= для региона ?region= (пустой — для всех регионов).
 */
final class LegalDocumentFormPage extends Page
{
    use AuthorizesAdminPage;
    use BuildsAdminForms;
    use LabelsLegalRegion;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Высота поля текста, строк. */
    private const int TEXT_ROWS = 16;

    /** Номер версии, с которой начинается документ вида и региона без предыдущих версий. */
    private const int FIRST_VERSION = 1;

    /** Адрес страницы в админке: /admin/page/legal-document-form. */
    protected ?string $alias = 'legal-document-form';

    /**
     * @param CoreContract         $core      ядро MoonShine
     * @param LegalDocumentService $documents правовые документы
     * @param Gate                 $gate      права доступа
     * @param UrlGenerator         $urls      адреса отправки формы
     * @param Request              $request   введённые значения после ошибки проверки
     */
    public function __construct(
        CoreContract $core,
        private readonly LegalDocumentService $documents,
        private readonly Gate $gate,
        private readonly UrlGenerator $urls,
        private readonly Request $request,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('legal.admin.edit');
    }

    /**
     * Хлебные крошки: админка → документы → правка.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminNestedBreadcrumbs(LegalDocumentIndexPage::class, __('legal.admin.title'), $this->getTitle());
    }

    /**
     * Право на открытие страницы: управление настройками.
     */
    protected function ability(): Ability
    {
        return Ability::ManageSettings;
    }

    /**
     * Форма с текстом последней версии.
     *
     * @return iterable<Box>
     */
    protected function components(): iterable
    {
        $type = ConsentType::tryFrom((string) $this->query('type'));
        $region = (string) $this->query('region', '');

        if ($type === null || ($region !== '' && ! $this->documents->regions()->contains($region))) {
            throw new EntityNotFoundException('Unknown legal document type or region.');
        }

        $title = __('admin.legal.form_title', [
            'type' => __('legal.types.' . $type->value),
            'region' => $this->legalRegionLabel($region),
        ]);
        $latest = $this->documents->latest($type, $region === '' ? null : $region);

        return [Box::make($title, [$this->form($type, $region, $latest)])];
    }

    /**
     * Форма новой версии: номер версии и поля предзаполнены последней версией.
     */
    private function form(ConsentType $type, string $region, ?LegalDocument $latest): FormBuilder
    {
        // Номер новой версии: первая, если документа ещё нет.
        $nextVersion = $latest === null ? self::FIRST_VERSION : $latest->getVersion() + 1;

        $form = FormBuilder::make($this->urls->route('admin.legal-documents.store'))->name('legal-document-form')
            ->fields([
                Hidden::make('type')->setValue($type->value),
                Hidden::make('region')->setValue($region),
                Text::make(__('legal.admin.fields.title'), 'title')
                    ->required()
                    ->customAttributes(['maxlength' => LegalDocument::TITLE_LENGTH]),
                Textarea::make(__('legal.admin.fields.text'), 'text')
                    ->required()
                    ->customAttributes(['rows' => self::TEXT_ROWS])
                    ->hint(__('legal.admin.version_hint', ['version' => $nextVersion])),
            ])
            ->fill([
                'title' => $this->request->old(
                    'title',
                    $latest === null ? __('legal.types.' . $type->value) : $latest->getTitle(),
                ),
                'text' => $this->request->old('text', $latest?->getText()),
            ]);

        return $this->adminSubmit($form, __('legal.admin.publish'));
    }
}
