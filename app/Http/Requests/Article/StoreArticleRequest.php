<?php

declare(strict_types=1);

namespace App\Http\Requests\Article;

use App\DTO\Article\ArticleData;
use App\DTO\Poll\PollData;
use App\Entities\Article;
use App\Entities\Poll;
use App\Entities\PollOption;
use App\Entities\Tag;
use App\Enums\Media\ImagePurpose;
use App\Repositories\ImageRepository;
use App\Services\Article\ArticleContent;
use App\Services\Article\ArticleSectionService;
use App\Services\Poll\PollService;
use App\Support\Settings\ContentLimits;
use App\Traits\AcceptsSeo;
use App\Traits\RequiresActiveUser;
use App\Traits\ValidatesUploadedImage;
use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Создание и правка статьи. При правке (PUT) обязательна версия, с которой начали редактирование.
 */
final class StoreArticleRequest extends FormRequest
{
    use AcceptsSeo;
    use RequiresActiveUser;
    use ValidatesUploadedImage;

    /** Наибольшее число разделов у статьи: больше в форме не выбрать. */
    private const int MAX_SECTIONS = 100;

    /** Буквы, цифры и знаки из названий технологий: «C#», «C++», «Node.js», «Laravel 12». */
    private const string TAG_PATTERN = '/^[\pL\pN][\pL\pN .+#_-]*$/u';

    /** Наименьшее число вариантов опроса: в опросе с одним вариантом выбора нет. */
    private const int MIN_POLL_OPTIONS = 2;

    /**
     * Правила проверки полей статьи, опроса и SEO.
     *
     * @return array<string, mixed>
     */
    public function rules(ArticleSectionService $sections, ContentLimits $limits, ImageRepository $images): array
    {
        return [
            'title' => ['required', 'string', 'max:' . Article::TITLE_LENGTH],
            'content' => ['required', 'string', 'max:' . ArticleContent::MAX_LENGTH, $this->contentRule($limits)],
            'cover' => ['nullable', 'uuid', $this->coverRule($images)],
            'summary' => ['nullable', 'string', 'max:' . Article::SUMMARY_LENGTH],
            'section_ids' => ['nullable', 'array', 'max:' . self::MAX_SECTIONS, $this->sectionsRule($sections)],
            'section_ids.*' => ['integer', 'min:1', 'distinct'],
            'tags' => ['nullable', 'array', 'max:' . $limits->articleTags()],
            'tags.*' => ['string', 'max:' . Tag::MAX_LENGTH, 'distinct:ignore_case', 'regex:' . self::TAG_PATTERN],
            'poll' => ['nullable', 'array'],
            'poll.question' => ['required_with:poll', 'string', 'max:' . Poll::QUESTION_LENGTH],
            'poll.options' => [
                'required_with:poll',
                'array',
                'min:' . self::MIN_POLL_OPTIONS,
                'max:' . $limits->pollOptions(),
            ],
            'poll.options.*' => ['required', 'string', 'max:' . PollOption::TEXT_LENGTH, 'distinct:ignore_case'],
            'poll.multiple' => ['required_with:poll', 'boolean'],
            'poll.max_choices' => ['nullable', 'integer', 'min:1', 'lte:' . $this->collect('poll.options')->count()],
            'poll.ends_at' => ['nullable', 'date', 'after:now'],
            'poll.allow_revote' => ['required_with:poll', 'boolean'],
            'poll.anonymous' => ['required_with:poll', 'boolean'],
            ...$this->seoRules(),
            'version' => [$this->isMethod('PUT') ? 'required' : 'prohibited', 'integer', 'min:1'],
        ];
    }

    /**
     * Варианты опроса, за которые уже голосовали, менять нельзя: голоса оказались бы отданы за другой ответ.
     *
     * @return list<callable(Validator): void>
     */
    public function after(PollService $polls): array
    {
        return [function (Validator $validator) use ($polls): void {
            $article = $this->route('article');
            if ($validator->errors()->isEmpty() && $article instanceof Article && is_array($this->input('poll'))) {
                if ($polls->optionsLocked($article, $this->pollData($this->input('poll')))) {
                    $validator->errors()->add('poll.options', __('messages.polls.options_locked'));
                }
            }
        }];
    }

    /**
     * Данные статьи из формы.
     */
    public function articleData(): ArticleData
    {
        return new ArticleData(
            title: $this->validated('title'),
            content: $this->validated('content'),
            summary: $this->validated('summary'),
            sectionIds: collect($this->validated('section_ids'))
                ->map(static fn (mixed $id): int => (int) $id)
                ->values(),
            tags: collect($this->validated('tags'))->values(),
            coverId: $this->validated('cover'),
            pollProvided: $this->has('poll'),
            poll: $this->pollData($this->validated('poll')),
            seo: $this->hasAny(['seo_title', 'seo_description']) ? $this->seoMeta() : null,
        );
    }

    /**
     * Версия статьи, с которой начали правку.
     */
    public function version(): int
    {
        return (int) $this->validated('version');
    }

    /**
     * Форма админки присылает теги одной строкой через запятую, API — списком. Пустые элементы отбрасываются.
     */
    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }
        if (is_array($tags)) {
            $this->merge(['tags' => collect($tags)
                ->map(static fn (mixed $tag): mixed => is_string($tag) ? Tag::normalize($tag) : $tag)
                ->reject(static fn (mixed $tag): bool => $tag === '')
                ->values()
                ->all()]);
        }
    }

    /**
     * Текст не пустой и картинок в нём не больше лимита из настроек.
     */
    private function contentRule(ContentLimits $limits): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($limits): void {
            if (! is_string($value)) {
                return;
            }
            if (ArticleContent::isEmpty($value)) {
                $fail(__('validation.required', ['attribute' => __('validation.attributes.content')]));
            }
            if (ArticleContent::countImages($value) > $limits->articleImages()) {
                $fail(__('messages.articles.too_many_images', ['max' => $limits->articleImages()]));
            }
        };
    }

    /**
     * Обложка загружена как обложка автором статьи или тем, кто её правит.
     */
    private function coverRule(ImageRepository $images): Closure
    {
        $article = $this->route('article');
        $owners = collect([$this->user(), $article instanceof Article ? $article->getUser() : null])->filter();
        $checkImage = $this->uploadedImageRule(
            $images,
            ImagePurpose::Cover,
            $owners,
            'messages.articles.cover_invalid',
        );

        return function (string $attribute, mixed $value, Closure $fail) use ($article, $checkImage): void {
            if ($article instanceof Article && $article->getCover()?->getUuid() === $value) {
                return;
            }
            $checkImage($attribute, $value, $fail);
        };
    }

    /**
     * Все выбранные разделы существуют; нечисловые идентификаторы отсекает правило section_ids.*.
     */
    private function sectionsRule(ArticleSectionService $sections): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($sections): void {
            if (! is_array($value)) {
                return;
            }
            $ids = collect($value)->filter(static fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id));
            $numericIds = $ids->map(static fn (mixed $id): int => (int) $id);
            if ($ids->count() === count($value) && ! $sections->allExist($numericIds)) {
                $fail(__('messages.articles.unknown_section'));
            }
        };
    }

    /**
     * Параметры опроса из формы; флаги приходят всегда, когда передан сам опрос.
     *
     * @param array<string, mixed>|null $poll
     */
    private function pollData(?array $poll): ?PollData
    {
        if ($poll === null) {
            return null;
        }
        $multiple = $this->boolean('poll.multiple');

        return new PollData(
            question: trim($poll['question']),
            options: collect($poll['options'])->map(static fn (string $option): string => trim($option))->values(),
            multiple: $multiple,
            maxChoices: $multiple && isset($poll['max_choices']) ? (int) $poll['max_choices'] : null,
            endsAt: empty($poll['ends_at']) ? null : new DateTimeImmutable($poll['ends_at']),
            allowRevote: $this->boolean('poll.allow_revote'),
            anonymous: $this->boolean('poll.anonymous'),
        );
    }
}
