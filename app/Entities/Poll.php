<?php

declare(strict_types=1);

namespace App\Entities;

use App\DTO\Poll\PollData;
use App\Traits\HasUuid;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Illuminate\Support\Collection;
use LaravelDoctrine\ORM\Contracts\UrlRoutable;

/**
 * Опрос статьи — не больше одного на статью (уникальный article_id).
 * Варианты принадлежат опросу целиком: cascade persist и orphanRemoval, порядок — по position.
 * Голос пользователя — один бюллетень на опрос (PollBallot), поэтому повторно проголосовать нельзя
 * даже при гонке запросов.
 */
#[ORM\Entity]
#[ORM\Table(name: 'polls')]
#[ORM\UniqueConstraint(name: 'polls_uuid_unique', columns: ['uuid'])]
#[ORM\UniqueConstraint(name: 'polls_article_unique', columns: ['article_id'])]
class Poll implements UrlRoutable
{
    use HasUuid;

    /** Длина вопроса — размер колонки question: вопрос выводится заголовком блока опроса. */
    public const int QUESTION_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::UUID_LENGTH)]
    private string $uuid;

    #[ORM\OneToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\Column(type: 'string', length: self::QUESTION_LENGTH)]
    private string $question;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $multiple = false;

    /** Сколько вариантов можно выбрать при множественном выборе; null — сколько угодно. */
    #[ORM\Column(name: 'max_choices', type: 'smallint', nullable: true)]
    private ?int $maxChoices = null;

    #[ORM\Column(name: 'ends_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $endsAt = null;

    #[ORM\Column(name: 'allow_revote', type: 'boolean', options: ['default' => false])]
    private bool $allowRevote = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $anonymous = true;

    /** @var DoctrineCollection<int, PollOption> */
    #[ORM\OneToMany(
        targetEntity: PollOption::class,
        mappedBy: 'poll',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private DoctrineCollection $options;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    private function __construct()
    {
        $this->options = new ArrayCollection();
    }

    /**
     * Новый опрос статьи с вариантами из формы.
     */
    public static function create(string $uuid, Article $article, PollData $settings): self
    {
        $poll = new self();
        $poll->uuid = $uuid;
        $poll->article = $article;
        $poll->configure($settings);
        $poll->replaceOptions($settings->options);

        return $poll;
    }

    /**
     * Вопрос, сроки и правила; варианты меняются отдельно, пока никто не голосовал.
     */
    public function configure(PollData $settings): void
    {
        $this->question = $settings->question;
        $this->multiple = $settings->multiple;
        $this->maxChoices = $settings->multiple ? $settings->maxChoices : null;
        $this->endsAt = $settings->endsAt;
        $this->allowRevote = $settings->allowRevote;
        $this->anonymous = $settings->anonymous;
    }

    /**
     * Заменяет варианты ответа.
     *
     * @param Collection<int, string> $texts тексты вариантов по порядку
     */
    public function replaceOptions(Collection $texts): void
    {
        $this->options->clear();
        $texts->values()->each(function (string $text, int $position): void {
            $this->options->add(PollOption::create($this, $text, $position));
        });
    }

    /**
     * Завершено ли голосование; время передаёт сервис из часов приложения.
     */
    public function isClosed(DateTimeImmutable $now): bool
    {
        return $this->endsAt !== null && $this->endsAt <= $now;
    }

    /**
     * Допустимый набор вариантов для бюллетеня: свои варианты, хотя бы один, не больше лимита.
     *
     * @param Collection<int, PollOption> $chosen
     */
    public function accepts(Collection $chosen): bool
    {
        $own = $chosen->every(fn (PollOption $option): bool => $this->options->contains($option));

        return $own && $chosen->isNotEmpty() && $chosen->count() <= $this->choiceLimit();
    }

    /**
     * Сколько вариантов можно выбрать.
     */
    public function choiceLimit(): int
    {
        if (! $this->multiple) {
            return 1;
        }

        // Лимит не задан (null) — можно выбрать все варианты.
        return $this->maxChoices ?? $this->options->count();
    }

    /**
     * Параметры опроса в виде данных формы.
     */
    public function settings(): PollData
    {
        return new PollData(
            question: $this->question,
            options: $this->getOptions()->map(static fn (PollOption $option): string => $option->getText()),
            multiple: $this->multiple,
            maxChoices: $this->maxChoices,
            endsAt: $this->endsAt,
            allowRevote: $this->allowRevote,
            anonymous: $this->anonymous,
        );
    }

    /**
     * Идентификатор опроса.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Ключ записи в адресе: UUID.
     */
    public static function getRouteKeyNameStatic(): string
    {
        return 'uuid';
    }

    /**
     * UUID опроса.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Статья опроса.
     */
    public function getArticle(): Article
    {
        return $this->article;
    }

    /**
     * Вопрос.
     */
    public function getQuestion(): string
    {
        return $this->question;
    }

    /**
     * Можно ли выбрать несколько вариантов.
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Окончание голосования.
     */
    public function getEndsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    /**
     * Можно ли изменить или отозвать голос.
     */
    public function allowsRevote(): bool
    {
        return $this->allowRevote;
    }

    /**
     * Скрыты ли имена проголосовавших.
     */
    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    /**
     * Варианты ответа по порядку.
     *
     * @return Collection<int, PollOption>
     */
    public function getOptions(): Collection
    {
        return collect($this->options->getValues());
    }

    /**
     * Вариант опроса по идентификатору.
     */
    public function option(int $id): ?PollOption
    {
        return $this->getOptions()->first(static fn (PollOption $option): bool => $option->getId() === $id);
    }
}
