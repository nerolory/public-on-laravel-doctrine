<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Localization;

use App\Entities\Language;
use App\Enums\User\Ability;
use App\Services\Localization\LanguageService;
use App\Support\Localization\LanguageFiles;
use App\Traits\AuthorizesAdminPage;
use App\Traits\BuildsAdminForms;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * Форма создания и изменения языка; код языка в адресе означает изменение.
 */
final class LanguageFormPage extends Page
{
    use AuthorizesAdminPage;
    use BuildsAdminForms;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Адрес страницы в админке: /admin/page/language-form. */
    protected ?string $alias = 'language-form';

    /**
     * @param CoreContract    $core      ядро MoonShine
     * @param LanguageService $languages языки
     * @param LanguageFiles   $files     папки переводов
     * @param Gate            $gate      права доступа
     * @param UrlGenerator    $urls      адреса отправки формы
     * @param Request         $request   введённые значения после ошибки проверки
     */
    public function __construct(
        CoreContract $core,
        private readonly LanguageService $languages,
        private readonly LanguageFiles $files,
        private readonly Gate $gate,
        private readonly UrlGenerator $urls,
        private readonly Request $request,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы: создание или изменение.
     */
    public function getTitle(): string
    {
        return __($this->query('code') ? 'languages.edit' : 'languages.create');
    }

    /**
     * Хлебные крошки: админка → языки → форма.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminNestedBreadcrumbs(LanguageIndexPage::class, __('languages.title'), $this->getTitle());
    }

    /**
     * Право на открытие страницы: управление настройками.
     */
    protected function ability(): Ability
    {
        return Ability::ManageSettings;
    }

    /**
     * Форма языка.
     *
     * @return iterable<Box>
     */
    protected function components(): iterable
    {
        $language = $this->editedLanguage();
        $action = $language === null
            ? $this->urls->route('admin.languages.store')
            : $this->urls->route('admin.languages.update', [$language->getCode()]);

        return [Box::make([
            $this->adminSubmit(
                FormBuilder::make($action)->name('language-form')
                    ->fields($this->fields($language))
                    ->fill($this->values($language)),
                __('settings.save'),
            ),
        ])];
    }

    /**
     * Значения полей: введённые до ошибки проверки или сохранённые; новый язык включён и не по умолчанию.
     *
     * @return array<string, mixed>
     */
    private function values(?Language $language): array
    {
        return [
            'code' => $this->request->old('code'),
            'name' => $this->request->old('name', $language?->getName()),
            'file' => $this->request->old('file', $language?->getFile()),
            'enabled' => $this->request->old('enabled', $language === null || $language->isEnabled()),
            'is_default' => $this->request->old('is_default', $language !== null && $language->isDefault()),
        ];
    }

    /**
     * Поля формы; код вводится только при создании.
     *
     * @return list<FieldContract>
     */
    private function fields(?Language $language): array
    {
        $files = $this->files->available();
        $code = Text::make(__('languages.fields.code'), 'code')
            ->required()
            ->customAttributes(['maxlength' => Language::CODE_LENGTH, 'pattern' => Language::CODE_REGEX])
            ->hint(__('languages.hints.code'));

        return [
            $language === null ? $code : $this->adminPutMethodField(),
            Text::make(__('languages.fields.name'), 'name')
                ->required()
                ->customAttributes(['maxlength' => Language::NAME_LENGTH])
                ->hint(__('languages.hints.name')),
            Select::make(__('languages.fields.file'), 'file')
                ->options($files->combine($files)->all())
                ->required()
                ->hint(__('languages.hints.file')),
            Switcher::make(__('languages.fields.enabled'), 'enabled'),
            Switcher::make(__('languages.fields.is_default'), 'is_default'),
        ];
    }

    /**
     * Изменяемый язык из адреса или null при создании; неизвестный код — 404.
     */
    private function editedLanguage(): ?Language
    {
        $code = $this->query('code');

        if ($code === null) {
            return null;
        }

        // Код из адреса сначала проверяется по шаблону, затем ищется в базе.
        $language = is_string($code) && preg_match(Language::CODE_PATTERN, $code) === 1
            ? $this->languages->find($code)
            : null;

        if ($language === null) {
            throw new EntityNotFoundException('Unknown language code in the query string.');
        }

        return $language;
    }
}
