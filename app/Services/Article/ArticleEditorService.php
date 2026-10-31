<?php

declare(strict_types=1);

namespace App\Services\Article;

use App\DTO\Article\ArticleData;
use App\Entities\Article;
use App\Entities\ArticleSection;
use App\Entities\Image;
use App\Entities\User;
use App\Repositories\ArticleRepository;
use App\Repositories\ArticleSectionRepository;
use App\Repositories\ImageRepository;
use App\Repositories\TagRepository;
use App\Services\Poll\PollService;
use App\Services\User\PermissionService;
use Closure;
use Doctrine\ORM\OptimisticLockException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Создание и правка статей автором и модератором: текст, разделы, теги, обложка и опрос.
 * Обложку и разделы проверяет StoreArticleRequest; здесь ловится только их удаление между проверкой и сохранением.
 */
final class ArticleEditorService
{
    /**
     * @param ArticleRepository        $articles    статьи
     * @param ArticleSectionRepository $sections    разделы
     * @param TagRepository            $tags        теги
     * @param ArticleContent           $content     очистка HTML
     * @param ImageRepository          $images      картинки
     * @param PollService              $polls       опросы
     * @param PermissionService        $permissions права модератора
     */
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ArticleSectionRepository $sections,
        private readonly TagRepository $tags,
        private readonly ArticleContent $content,
        private readonly ImageRepository $images,
        private readonly PollService $polls,
        private readonly PermissionService $permissions,
    ) {
    }

    /**
     * Новая статья на модерации. SEO-поля сохраняются, только если автор — модератор.
     *
     * @throws ValidationException обложку или раздел удалили после проверки формы
     */
    public function create(ArticleData $data, User $author): void
    {
        $owners = collect([$author]);
        $article = Article::create(
            title: $data->title,
            content: $this->content->prepare($data->content, $owners),
            user: $author,
            sections: $this->sections($data->sectionIds),
            summary: $data->summary,
            cover: $this->cover($data->coverId, current: null),
        );

        if ($data->seo !== null && $this->permissions->canModerate($author)) {
            $article->changeSeo($data->seo);
        }

        $this->persist($article, $data);
    }

    /**
     * Правка модератором; статус не меняется. Картинки в тексте могут принадлежать автору или редактору.
     *
     * @throws OptimisticLockException статью изменили после открытия формы
     * @throws ValidationException     обложку или раздел удалили после проверки формы
     */
    public function update(Article $article, ArticleData $data, int $version, User $editor): void
    {
        $this->edit(
            $article,
            $data,
            $version,
            collect([$article->getUser(), $editor]),
            static function (string $content, Collection $sections) use ($article, $data): void {
                $article->update($data->title, $content, $data->summary, $sections);

                if ($data->seo !== null) {
                    $article->changeSeo($data->seo);
                }
            },
        );
    }

    /**
     * Правка автором отправляет статью на повторную модерацию.
     *
     * @throws OptimisticLockException статью изменили после открытия формы
     * @throws ValidationException     обложку или раздел удалили после проверки формы
     */
    public function revise(Article $article, ArticleData $data, int $version): void
    {
        $this->edit(
            $article,
            $data,
            $version,
            collect([$article->getUser()]),
            static function (string $content, Collection $sections) use ($article, $data): void {
                $article->reviseByAuthor($data->title, $content, $data->summary, $sections);
            },
        );
    }

    /**
     * Общий путь правки: проверка версии, очистка текста, разделы, затем изменение статьи и её вложения.
     *
     * @param Collection<int, User>                                  $owners владельцы картинок, оставляемых в тексте
     * @param Closure(string, Collection<int, ArticleSection>): void $change изменение статьи по домену
     *
     * @throws OptimisticLockException статью изменили после открытия формы
     * @throws ValidationException     обложку или раздел удалили после проверки формы
     */
    private function edit(
        Article $article,
        ArticleData $data,
        int $version,
        Collection $owners,
        Closure $change,
    ): void {
        $this->articles->lockVersion($article, $version);
        $change($this->content->prepare($data->content, $owners), $this->sections($data->sectionIds));

        $this->saveAttachments($article, $data);
    }

    /**
     * Обложка после правки текста; затем теги, опрос и статья.
     */
    private function saveAttachments(Article $article, ArticleData $data): void
    {
        $article->changeCover($this->cover($data->coverId, $article->getCover()));
        $this->persist($article, $data);
    }

    /**
     * Теги статьи сохраняются вместе со статьёй; опрос — после неё, ему нужен идентификатор статьи.
     */
    private function persist(Article $article, ArticleData $data): void
    {
        $article->replaceTags($this->tags->resolve($data->tags));
        $this->articles->save($article);

        if ($data->pollProvided) {
            $this->polls->sync($article, $data->poll);
        }
    }

    /**
     * Обложка по UUID: текущая остаётся как есть, новую проверил запрос.
     *
     * @throws ValidationException обложку удалили после проверки формы
     */
    private function cover(?string $uuid, ?Image $current): ?Image
    {
        if ($uuid === null) {
            return null;
        }

        if ($current !== null && $current->getUuid() === $uuid) {
            return $current;
        }

        return $this->images->findByUuid($uuid)
            ?? throw ValidationException::withMessages(['cover' => __('messages.articles.cover_invalid')]);
    }

    /**
     * Разделы статьи по идентификаторам.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, ArticleSection>
     *
     * @throws ValidationException раздел удалили после проверки формы
     */
    private function sections(Collection $ids): Collection
    {
        $ids = $ids->unique()->values();
        $sections = $this->sections->findByIds($ids);

        if ($sections->count() !== $ids->count()) {
            throw ValidationException::withMessages(['section_ids' => __('messages.articles.unknown_section')]);
        }

        return $sections;
    }
}
