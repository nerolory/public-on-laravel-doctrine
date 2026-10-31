<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\ArticleSection;

use App\Entities\ArticleSection;
use App\Entities\SeoMeta;
use App\Http\Requests\Common\SectionFilterRequest;
use App\Services\Article\ArticleSectionService;
use App\Traits\BuildsAdminForms;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * Создание и правка раздела. Адрес и SEO-поля задаются после создания: адрес строится из названия.
 */
final class ArticleSectionFormPage extends Page
{
    use BuildsAdminForms;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Высота поля описания раздела, строк. */
    private const int SEO_DESCRIPTION_ROWS = 2;

    /** Адрес страницы в админке: /admin/page/article-section-form. */
    protected ?string $alias = 'article-section-form';

    /**
     * @param CoreContract          $core     ядро MoonShine
     * @param ArticleSectionService $sections разделы
     * @param UrlGenerator          $urls     адреса отправки формы
     * @param Request               $request  введённые значения после ошибки проверки
     */
    public function __construct(
        CoreContract $core,
        private readonly ArticleSectionService $sections,
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
        return __($this->query('id') ? 'admin.sections.edit' : 'admin.sections.new');
    }

    /**
     * Хлебные крошки.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminNestedBreadcrumbs(
            ArticleSectionIndexPage::class,
            __('admin.sections.title'),
            $this->getTitle(),
        );
    }

    /**
     * Форма раздела.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $id = $this->queryId();
        $section = $id === null ? null : $this->sections->find($id);

        if ($id !== null && $section === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(ArticleSection::class, ['id' => (string) $id]);
        }

        $action = $section === null
            ? $this->urls->route('admin.sections.store')
            : $this->urls->route('admin.sections.update', [$section->getId()]);

        return [Box::make([
            $this->adminSubmit(
                FormBuilder::make($action)
                    ->name('section-form')
                    ->fields($this->fields($section))
                    ->fill($this->values($section)),
                __($section === null ? 'admin.sections.create' : 'admin.common.save'),
            ),
        ])];
    }

    /**
     * Значения полей: введённые до ошибки проверки или сохранённые в разделе.
     *
     * @return array<string, mixed>
     */
    private function values(?ArticleSection $section): array
    {
        return [
            'name' => $this->request->old('name', $section?->getName()),
            'slug' => $this->request->old('slug', $section?->getSlug()),
            'parent_id' => $this->request->old('parent_id', $section?->getParent()?->getId()),
            'seo_title' => $this->request->old('seo_title', $section?->getSeo()->title()),
            'seo_description' => $this->request->old('seo_description', $section?->getSeo()->description()),
        ];
    }

    /**
     * Поля формы; адрес и SEO-поля есть только у существующего раздела.
     *
     * @return list<object>
     */
    private function fields(?ArticleSection $section): array
    {
        // Шаблон адреса для браузера: выражение без разделителей и якорей.
        $slugPattern = SectionFilterRequest::SLUG_EXPRESSION;
        $parent = Select::make(__('admin.sections.fields.parent'), 'parent_id')
            ->options($this->sections->parentOptions($section)->all())
            ->nullable()
            ->searchable();
        $name = Text::make(__('admin.sections.fields.name'), 'name')
            ->required()
            ->customAttributes(['maxlength' => ArticleSection::NAME_LENGTH]);

        if ($section === null) {
            return [$name, $parent];
        }

        return [
            $this->adminPutMethodField(),
            $name,
            Text::make(__('admin.sections.fields.slug'), 'slug')
                ->required()
                ->customAttributes(['maxlength' => ArticleSection::SLUG_LENGTH, 'pattern' => $slugPattern])
                ->hint(__('admin.sections.slug_hint')),
            $parent,
            Text::make(__('admin.seo.title'), 'seo_title')
                ->hint(__('admin.seo.section_title_hint'))
                ->customAttributes(['maxlength' => SeoMeta::TITLE_LENGTH]),
            Textarea::make(__('admin.seo.description'), 'seo_description')
                ->customAttributes(['rows' => self::SEO_DESCRIPTION_ROWS, 'maxlength' => SeoMeta::DESCRIPTION_LENGTH]),
        ];
    }
}
