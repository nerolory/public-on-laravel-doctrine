<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Article;

use App\DTO\Article\ArticleEditData;
use App\DTO\ArticleSection\SectionNodeData;
use App\Entities\Article;
use App\Entities\SeoMeta;
use App\Entities\Tag;
use App\Services\Article\ArticlePageService;
use App\Services\Article\ArticleSectionService;
use App\Support\Settings\ContentLimits;
use App\Traits\BuildsAdminForms;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use MoonShine\AssetManager\Raw;
use MoonShine\Contracts\AssetManager\AssetElementContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * Создание и правка статьи в админке тем же редактором, что и на сайте.
 */
final class ArticleFormPage extends Page
{
    use BuildsAdminForms;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Разделитель тегов в поле формы. */
    private const string TAG_SEPARATOR = ', ';

    /** Высота поля текста статьи, строк. */
    private const int CONTENT_ROWS = 10;

    /** Высота коротких текстовых полей: анонса и описания для поисковых систем, строк. */
    private const int SHORT_TEXT_ROWS = 2;

    /** Адрес страницы в админке: /admin/page/article-form. */
    protected ?string $alias = 'article-form';

    /**
     * @param CoreContract          $core     ядро MoonShine
     * @param ArticlePageService    $pages    статья для формы правки
     * @param ArticleSectionService $sections разделы
     * @param ContentLimits         $limits   лимиты формы
     * @param Vite                  $vite     сборка редактора админки
     * @param UrlGenerator          $urls     адреса отправки формы
     * @param Request               $request  введённые значения после ошибки проверки
     */
    public function __construct(
        CoreContract $core,
        private readonly ArticlePageService $pages,
        private readonly ArticleSectionService $sections,
        private readonly ContentLimits $limits,
        private readonly Vite $vite,
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
        return __($this->query('id') ? 'admin.articles.edit' : 'admin.articles.new');
    }

    /**
     * Хлебные крошки.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminNestedBreadcrumbs(ArticleIndexPage::class, __('admin.menu.articles'), $this->getTitle());
    }

    /**
     * Редактор статьи из сборки frontend/editor.
     *
     * @return list<AssetElementContract>
     */
    protected function assets(): array
    {
        return [Raw::make((string) ($this->vite)(['article-editor.ts']))];
    }

    /**
     * Форма статьи.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $id = $this->queryId();
        $entity = $id === null ? null : $this->pages->find($id);

        if ($id !== null && $entity === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(Article::class, ['id' => (string) $id]);
        }

        $article = $entity === null ? null : $this->pages->editForm($entity);
        $action = $article === null
            ? $this->urls->route('admin.articles.store')
            : $this->urls->route('admin.articles.update', [$article->id]);

        return [
            Box::make([
                $this->adminSubmit(
                    FormBuilder::make($action)
                        ->name('article-form')
                        ->fields($this->fields($article))
                        ->fill($this->values($article)),
                    __($article === null ? 'admin.articles.create' : 'admin.common.save'),
                ),
            ]),
        ];
    }

    /**
     * Поля формы; подписи редактора берутся из lang/{язык}/editor.php.
     *
     * @return list<object>
     */
    private function fields(?ArticleEditData $article): array
    {
        // JSON подписей редактора для data-атрибута полей с редактором.
        $labels = json_encode(__('editor'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            ...($article === null ? [] : $this->versionFields($article)),
            Text::make(__('admin.articles.fields.title'), 'title')
                ->required()
                ->customAttributes(['maxlength' => Article::TITLE_LENGTH]),
            Hidden::make('cover')->customAttributes([
                'data-cover-field' => true,
                'data-url' => $article?->cover?->url,
                'data-labels' => $labels,
            ]),
            $this->contentField($labels),
            Textarea::make(__('admin.articles.fields.summary'), 'summary')
                ->hint(__('admin.articles.fields.summary_hint', ['length' => Article::SUMMARY_LENGTH]))
                ->customAttributes(['rows' => self::SHORT_TEXT_ROWS, 'maxlength' => Article::SUMMARY_LENGTH]),
            ...$this->seoFields(),
            $this->tagsField(),
            Select::make(__('admin.articles.fields.sections'), 'section_ids')
                ->options($this->sectionOptions())
                ->multiple()
                ->searchable()
                ->nullable(),
        ];
    }

    /**
     * Скрытые поля правки: метод отправки и версия статьи для проверки конфликта правок.
     *
     * @return list<Hidden>
     */
    private function versionFields(ArticleEditData $article): array
    {
        return [
            $this->adminPutMethodField(),
            Hidden::make('version')->setValue((string) $article->version),
        ];
    }

    /**
     * Поле текста статьи с редактором и лимитами формы.
     */
    private function contentField(string $labels): Textarea
    {
        // JSON лимитов формы для data-атрибута поля текста.
        $limits = json_encode($this->limits->forms()->toArray(), JSON_THROW_ON_ERROR);

        return Textarea::make(__('admin.articles.fields.content'), 'content')->required()->customAttributes([
            'rows' => self::CONTENT_ROWS,
            'data-rich-editor' => true,
            'data-labels' => $labels,
            'data-limits' => $limits,
        ]);
    }

    /**
     * Поля SEO: заголовок и описание для поисковых систем.
     *
     * @return list<Text|Textarea>
     */
    private function seoFields(): array
    {
        return [
            Text::make(__('admin.seo.title'), 'seo_title')
                ->hint(__('admin.seo.title_hint'))
                ->customAttributes(['maxlength' => SeoMeta::TITLE_LENGTH]),
            Textarea::make(__('admin.seo.description'), 'seo_description')
                ->hint(__('admin.seo.description_hint'))
                ->customAttributes(['rows' => self::SHORT_TEXT_ROWS, 'maxlength' => SeoMeta::DESCRIPTION_LENGTH]),
        ];
    }

    /**
     * Поле тегов; длина поля вмещает все теги вместе с разделителями.
     */
    private function tagsField(): Text
    {
        $tags = $this->limits->articleTags();
        $tagsLength = $tags * (Tag::MAX_LENGTH + mb_strlen(self::TAG_SEPARATOR));

        return Text::make(__('admin.articles.fields.tags'), 'tags')
            ->hint(__('admin.articles.fields.tags_hint', ['max' => $tags]))
            ->customAttributes(['maxlength' => $tagsLength]);
    }

    /**
     * Значения полей: введённые до ошибки проверки или сохранённые в статье.
     *
     * @return array<string, mixed>
     */
    private function values(?ArticleEditData $article): array
    {
        return [
            'title' => $this->request->old('title', $article?->title),
            'content' => $this->request->old('content', $article?->content),
            'summary' => $this->request->old('summary', $article?->summary),
            'cover' => $this->request->old('cover', $article?->cover?->id),
            'seo_title' => $this->request->old('seo_title', $article?->seo->title()),
            'seo_description' => $this->request->old('seo_description', $article?->seo->description()),
            'tags' => $this->request->old('tags', $article?->tags->implode(self::TAG_SEPARATOR)),
            'section_ids' => $this->request->old('section_ids', $article?->sectionIds->all()),
        ];
    }

    /**
     * Разделы для выбора, сгруппированные по корневому разделу.
     *
     * @return array<string, array<int, string>>
     */
    private function sectionOptions(): array
    {
        $groups = [];
        $group = '';
        $this->sections->all()->each(function (SectionNodeData $node) use (&$groups, &$group): void {
            $group = $node->level === 0 ? $node->name : $group;
            $groups[$group][$node->id] = $node->indentedName();
        });

        return $groups;
    }
}
