<?php

declare(strict_types=1);

namespace App\Entities;

use App\Contracts\Moderatable;
use App\Enums\Article\ArticleStatus;
use App\Enums\Moderation\ModerationStatus;
use App\Traits\HasOwner;
use App\Traits\HasPersistedId;
use App\Traits\NormalizesText;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Illuminate\Support\Collection;
use LaravelDoctrine\ORM\Contracts\UrlRoutable;

/**
 * Статья. Возможности Doctrine и Gedmo в одной сущности:
 * - Timestampable заполняет даты создания и изменения;
 * - SoftDeleteable превращает удаление в архивирование: фильтр скрывает статью, она остаётся в истории;
 * - Loggable пишет версии заголовка, текста и статуса в журнал article_log_entries;
 * - #[ORM\Version] даёт оптимистическую блокировку при одновременной правке.
 */
#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[ORM\Index(name: 'articles_published_idx', columns: ['is_published', 'id'])]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[Gedmo\Loggable(logEntryClass: ArticleLogEntry::class)]
class Article implements Moderatable, UrlRoutable
{
    use HasOwner;
    use HasPersistedId;
    use NormalizesText;

    /** Длина краткого описания — размер колонки summary: столько символов поисковики показывают в сниппете. */
    public const int SUMMARY_LENGTH = 160;

    /** Длина заголовка статьи — размер колонки title: заголовок выводится в одну-две строки ленты. */
    public const int TITLE_LENGTH = 255;

    /** Многоточие в конце обрезанного анонса. */
    private const string ELLIPSIS = '…';

    /** Графики и подписи к картинкам в анонс не попадают; figure, обрезанная запросом, убирается до конца текста. */
    private const array FIGURE_PATTERNS = ['#<figure\b.*?</figure>#is', '#<figure\b.*$#is'];

    /** Блочные теги заменяются пробелом, чтобы соседние абзацы не склеивались в одно слово. */
    private const string BLOCK_TAGS_PATTERN = '#</?(?:p|div|br|li|h[1-6]|blockquote|tr|td|th|figcaption)\b[^>]*>#i';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: self::TITLE_LENGTH)]
    private string $title;

    #[Gedmo\Versioned]
    #[ORM\Column(type: 'text')]
    private string $content;

    /** Краткое описание: анонс в ленте и описание для поисковиков. */
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: self::SUMMARY_LENGTH, nullable: true)]
    private ?string $summary = null;

    #[Gedmo\Versioned]
    #[ORM\Column(name: 'is_published', type: 'boolean', options: ['default' => false])]
    private bool $isPublished = false;

    #[Gedmo\Versioned]
    #[ORM\Column(name: 'is_rejected', type: 'boolean', options: ['default' => false])]
    private bool $isRejected = false;

    /** Последняя причина отклонения; видна только автору, пока статья не опубликована. */
    #[ORM\Column(name: 'rejection_reason', type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Image::class)]
    #[ORM\JoinColumn(name: 'cover_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Image $cover = null;

    /** @var DoctrineCollection<int, ArticleSection> */
    #[ORM\ManyToMany(targetEntity: ArticleSection::class)]
    #[ORM\JoinTable(name: 'article_section_links')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'section_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private DoctrineCollection $sections;

    #[ORM\Embedded(class: SeoMeta::class, columnPrefix: 'seo_')]
    private SeoMeta $seo;

    /** @var DoctrineCollection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'article_tags')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private DoctrineCollection $tags;

    #[ORM\Column(name: 'view_count', type: 'integer', options: ['default' => 0])]
    private int $viewCount = 0;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    /** Причина, которую автор указал при удалении статьи в архив. */
    #[ORM\Column(name: 'deletion_reason', type: 'text', nullable: true)]
    private ?string $deletionReason = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    private function __construct()
    {
        $this->sections = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->seo = new SeoMeta();
    }

    /**
     * Ключ записи в адресе: id. Запись ищется запросом, поэтому к ней применяются фильтры Doctrine.
     */
    public static function getRouteKeyNameStatic(): string
    {
        return 'id';
    }

    /**
     * Новая статья на модерации.
     *
     * @param iterable<ArticleSection> $sections разделы статьи
     */
    public static function create(
        string $title,
        string $content,
        User $user,
        iterable $sections = [],
        ?string $summary = null,
        ?Image $cover = null,
    ): self {
        $article = new self();
        $article->title = $title;
        $article->content = $content;
        $article->user = $user;
        $article->summary = self::cleanSummary($summary);
        $article->cover = $cover;
        $article->replaceSections($sections);

        return $article;
    }

    /**
     * Анонс: краткое описание, без него — начало текста с многоточием в пределах длины описания.
     */
    public static function excerptOf(?string $summary, string $content): string
    {
        $summary = self::cleanSummary($summary);
        if ($summary !== null) {
            return $summary;
        }
        $content = self::replacePattern(self::FIGURE_PATTERNS, ' ', $content);
        $text = strip_tags(self::replacePattern(self::BLOCK_TAGS_PATTERN, ' ', $content));
        // Сущности HTML превращаются в символы, пробелы и переносы схлопываются в один пробел.
        $text = self::collapseSpaces(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
        if (mb_strlen($text) <= self::SUMMARY_LENGTH) {
            return $text;
        }

        // Многоточие входит в длину анонса.
        return rtrim(mb_substr($text, 0, self::SUMMARY_LENGTH - mb_strlen(self::ELLIPSIS))) . self::ELLIPSIS;
    }

    /**
     * Описание без лишних пробелов, обрезанное до допустимой длины; пустое — null.
     */
    public static function cleanSummary(?string $summary): ?string
    {
        $summary = self::blankToNull($summary);

        return $summary === null ? null : mb_substr($summary, 0, self::SUMMARY_LENGTH);
    }

    /**
     * Правка содержания модератором; статус не меняется.
     *
     * @param iterable<ArticleSection> $sections разделы статьи
     */
    public function update(string $title, string $content, ?string $summary, iterable $sections = []): void
    {
        $this->title = $title;
        $this->content = $content;
        $this->summary = self::cleanSummary($summary);
        $this->replaceSections($sections);
    }

    /**
     * Правка автором: любое изменение отправляет статью на повторную модерацию.
     *
     * @param iterable<ArticleSection> $sections разделы статьи
     */
    public function reviseByAuthor(string $title, string $content, ?string $summary, iterable $sections): void
    {
        $this->update($title, $content, $summary, $sections);
        $this->sendToModeration();
    }

    /**
     * Заменяет разделы статьи.
     *
     * @param iterable<ArticleSection> $sections разделы статьи
     */
    public function replaceSections(iterable $sections): void
    {
        $this->sections->clear();
        foreach ($sections as $section) {
            $this->addSection($section);
        }
    }

    /**
     * Заменяет теги статьи; повторы пропускаются.
     *
     * @param iterable<Tag> $tags теги статьи
     */
    public function replaceTags(iterable $tags): void
    {
        $this->tags->clear();
        foreach ($tags as $tag) {
            if (! $this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }
    }

    /**
     * Добавляет раздел, если его ещё нет у статьи.
     */
    public function addSection(ArticleSection $section): void
    {
        if (! $this->sections->contains($section)) {
            $this->sections->add($section);
        }
    }

    /**
     * SEO-поля меняет модератор; автор правит только содержание.
     */
    public function changeSeo(SeoMeta $seo): void
    {
        $this->seo = $seo;
    }

    /**
     * Меняет или убирает обложку.
     */
    public function changeCover(?Image $cover): void
    {
        $this->cover = $cover;
    }

    /**
     * Публикует статью; дата первой публикации сохраняется.
     */
    public function publish(): void
    {
        $this->isPublished = true;
        $this->isRejected = false;
        $this->rejectionReason = null;
        $this->publishedAt ??= new DateTimeImmutable();
    }

    /**
     * Отклоняет статью с причиной для автора.
     */
    public function reject(string $reason): void
    {
        $reason = ModerationStatus::normalizeReason($reason);
        $this->isPublished = false;
        $this->isRejected = true;
        $this->rejectionReason = $reason;
    }

    /**
     * Возвращает статью на модерацию.
     */
    public function sendToModeration(): void
    {
        $this->isPublished = false;
        $this->isRejected = false;
        $this->rejectionReason = null;
    }

    /**
     * Причина удаления автором. Само удаление в архив выполняет SoftDeleteable при remove().
     */
    public function markForArchive(string $reason): void
    {
        $this->deletionReason = trim($reason);
        $this->isPublished = false;
    }

    /**
     * Состояние статьи для автора и модератора.
     */
    public function status(): ArticleStatus
    {
        if ($this->deletedAt !== null) {
            return ArticleStatus::Archived;
        }

        return ArticleStatus::fromFlags($this->isPublished, $this->isRejected);
    }

    /**
     * Анонс статьи.
     */
    public function excerpt(): string
    {
        return self::excerptOf($this->summary, $this->content);
    }

    /** Идентификатор статьи; до сохранения — null. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Заголовок. */
    public function getTitle(): string
    {
        return $this->title;
    }

    /** HTML текста в каноническом виде. */
    public function getContent(): string
    {
        return $this->content;
    }

    /** Краткое описание, заданное автором. */
    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /** Обложка. */
    public function getCover(): ?Image
    {
        return $this->cover;
    }

    /** Последняя причина отклонения. */
    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    /** Опубликована ли статья. */
    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    /** Автор статьи. */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Разделы статьи.
     *
     * @return Collection<int, ArticleSection>
     */
    public function getSections(): Collection
    {
        return collect($this->sections->getValues());
    }

    /**
     * Теги статьи.
     *
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return collect($this->tags->getValues());
    }

    /** Число просмотров. */
    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    /** SEO-поля, заданные модератором. */
    public function getSeo(): SeoMeta
    {
        return $this->seo;
    }

    /** Дата создания. */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Дата последнего изменения. */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Дата первой публикации. */
    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    /** Номер версии для оптимистической блокировки. */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Владелец статьи — её автор.
     */
    private function owner(): User
    {
        return $this->user;
    }
}
