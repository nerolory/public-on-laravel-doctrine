<?php

declare(strict_types=1);

namespace App\Services\Article;

use App\DTO\ArticleSection\SectionFilterRowData;
use App\DTO\ArticleSection\SectionNodeData;
use App\DTO\ArticleSection\SectionRowData;
use App\DTO\Common\PageData;
use App\Entities\ArticleSection;
use App\Entities\SeoMeta;
use App\Repositories\ArticleRepository;
use App\Repositories\ArticleSectionRepository;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Разделы статей: дерево, перенос, удаление и ряды фильтра ленты.
 */
final class ArticleSectionService
{
    /** Ключ первого ряда фильтра: корневые разделы. */
    private const string ROOT_ROW = 'root';

    /** Подпись кнопки «все» в корневом ряду фильтра. */
    private const string ALL_LABEL = 'all';

    /** Подпись кнопки «весь раздел» в ряду подразделов фильтра. */
    private const string WHOLE_SECTION_LABEL = 'whole_section';

    /**
     * @param ArticleSectionRepository $sections разделы
     * @param ArticleRepository        $articles статьи
     */
    public function __construct(
        private readonly ArticleSectionRepository $sections,
        private readonly ArticleRepository $articles,
    ) {
    }

    /**
     * Новый раздел; без родителя — корневой. Родителя проверил StoreArticleSectionRequest.
     *
     * @throws ValidationException родителя удалили после проверки формы
     */
    public function create(string $name, ?int $parentId = null): ArticleSection
    {
        $section = ArticleSection::create($name, $this->parent($parentId));
        $this->sections->save($section);

        return $section;
    }

    /**
     * Меняет название, адрес, родителя и SEO-поля раздела. Родителя проверил StoreArticleSectionRequest.
     *
     * @throws ValidationException родителя удалили после проверки формы
     */
    public function update(
        ArticleSection $section,
        string $name,
        string $slug,
        ?int $parentId,
        SeoMeta $seo,
    ): void {
        $section->update($name, $slug, $this->parent($parentId), $seo);
        $this->sections->save($section);
    }

    /**
     * Удаляет раздел: статьи получают родительский раздел, подразделы поднимаются к родителю.
     */
    public function delete(ArticleSection $section): void
    {
        $this->sections->delete($section);
    }

    /**
     * Можно ли удалить раздел: корневой — только без подразделов и статей.
     */
    public function canDelete(ArticleSection $section): bool
    {
        return ArticleSection::isDeletable(
            root: $section->isRoot(),
            hasChildren: $this->sections->hasChildren($section),
            hasArticles: $this->sections->hasArticles($section),
        );
    }

    /**
     * Можно ли сделать раздел подразделом данного родителя: нельзя переносить его в самого себя и в своё поддерево.
     */
    public function canMoveTo(int $id, ?int $parentId): bool
    {
        $section = $this->sections->find($id);

        return $parentId === null || $section === null || ! $this->sections->subtreeIds($section)->contains($parentId);
    }

    /**
     * Существуют ли все разделы.
     *
     * @param Collection<int, int> $ids
     */
    public function allExist(Collection $ids): bool
    {
        $ids = $ids->unique()->values();

        return $this->sections->findByIds($ids)->count() === $ids->count();
    }

    /**
     * Варианты родителя: все разделы, кроме самого раздела и его подразделов.
     *
     * @return Collection<int, string> название с отступом по идентификатору раздела
     */
    public function parentOptions(?ArticleSection $except = null): Collection
    {
        $excluded = $except === null ? collect() : $this->sections->subtreeIds($except);

        return $this->sections->all()
            ->reject(static fn (SectionNodeData $node): bool => $excluded->contains($node->id))
            ->mapWithKeys(static fn (SectionNodeData $node): array => [$node->id => $node->indentedName()]);
    }

    /**
     * Раздел по идентификатору или null.
     */
    public function find(int $id): ?ArticleSection
    {
        return $this->sections->find($id);
    }

    /**
     * Раздел по названию.
     */
    public function findByName(string $name): ?ArticleSection
    {
        return $this->sections->findByName($name);
    }

    /**
     * Раздел по адресу.
     *
     * @throws EntityNotFoundException раздела с таким адресом нет
     */
    public function getBySlug(string $slug): ArticleSection
    {
        return $this->sections->findBySlug($slug)
            ?? throw EntityNotFoundException::fromClassNameAndIdentifier(ArticleSection::class, ['slug' => $slug]);
    }

    /**
     * Путь от корня до раздела включительно.
     *
     * @return Collection<int, ArticleSection>
     */
    public function path(ArticleSection $section): Collection
    {
        return $this->sections->path($section);
    }

    /**
     * Занято ли название другим разделом.
     */
    public function nameInUse(string $name, ?int $exceptId = null): bool
    {
        return $this->sections->nameInUse($name, $exceptId);
    }

    /**
     * Занят ли адрес другим разделом.
     */
    public function slugInUse(string $slug, ?int $exceptId = null): bool
    {
        return $this->sections->slugInUse($slug, $exceptId);
    }

    /**
     * Есть ли раздел.
     */
    public function exists(int $id): bool
    {
        return $this->sections->find($id) !== null;
    }

    /**
     * Все разделы деревом.
     *
     * @return Collection<int, SectionNodeData>
     */
    public function all(): Collection
    {
        return $this->sections->all();
    }

    /**
     * Страница списка разделов в админке.
     *
     * @return PageData<SectionRowData>
     */
    public function page(int $page, int $limit): PageData
    {
        return $this->sections->page($page, $limit);
    }

    /**
     * Добавляет раздел статьям с указанными заголовками, у которых ещё нет разделов.
     *
     * @param Collection<int, string> $titles
     *
     * @return int число статей, получивших раздел
     */
    public function assignToArticles(ArticleSection $section, Collection $titles): int
    {
        return $this->articles->assignSectionByTitles($section, $titles);
    }

    /**
     * Ряды фильтра на главной: первый — корневые разделы, дальше по ряду на каждый уровень пути
     * до выбранного раздела, если у узла есть подразделы. Неизвестный адрес даёт только первый ряд.
     *
     * @return Collection<int, SectionFilterRowData>
     */
    public function filterRows(?string $selected): Collection
    {
        $nodes = $this->sections->all();
        $path = self::pathTo($nodes, $selected);
        $childrenOf = static fn (?string $parent): Collection => $nodes
            ->filter(static fn (SectionNodeData $node): bool => $node->parent === $parent)
            ->values();
        $rows = collect([new SectionFilterRowData(
            key: self::ROOT_ROW,
            all: null,
            allLabel: self::ALL_LABEL,
            active: $path->first(),
            items: $childrenOf(parent: null),
        )]);

        foreach ($path as $index => $slug) {
            $items = $childrenOf($slug);

            if ($items->isNotEmpty()) {
                $rows->push(new SectionFilterRowData(
                    key: $slug,
                    all: $slug,
                    allLabel: self::WHOLE_SECTION_LABEL,
                    active: $path->get($index + 1),
                    items: $items,
                ));
            }
        }

        return $rows;
    }

    /**
     * Адреса разделов от корня до выбранного; неизвестный адрес даёт пустой путь.
     *
     * @param Collection<int, SectionNodeData> $nodes
     *
     * @return Collection<int, string>
     */
    private static function pathTo(Collection $nodes, ?string $selected): Collection
    {
        $bySlug = $nodes->keyBy(static fn (SectionNodeData $node): string => $node->slug);
        $path = collect();
        // Идём от выбранного раздела к корню по ссылке на родителя; у корня родителя нет.
        $node = $selected === null ? null : $bySlug->get($selected);

        while ($node !== null) {
            $path->prepend($node->slug);
            $node = $node->parent === null ? null : $bySlug->get($node->parent);
        }

        return $path->values();
    }

    /**
     * Родительский раздел по идентификатору.
     *
     * @throws ValidationException родителя удалили после проверки формы
     */
    private function parent(?int $id): ?ArticleSection
    {
        if ($id === null) {
            return null;
        }

        return $this->sections->find($id)
            ?? throw ValidationException::withMessages(['parent_id' => __('messages.sections.parent_missing')]);
    }
}
