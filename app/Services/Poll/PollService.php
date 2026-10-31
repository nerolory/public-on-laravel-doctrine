<?php

declare(strict_types=1);

namespace App\Services\Poll;

use App\DTO\Common\PageData;
use App\DTO\Poll\PollData;
use App\DTO\Poll\PollOptionViewData;
use App\DTO\Poll\PollResultsData;
use App\DTO\Poll\PollSettingsData;
use App\DTO\Poll\PollViewData;
use App\DTO\User\UserCardData;
use App\Entities\Article;
use App\Entities\Poll;
use App\Entities\PollBallot;
use App\Entities\PollOption;
use App\Entities\User;
use App\Enums\Poll\PollProblem;
use App\Repositories\PollRepository;
use App\Services\User\UserService;
use App\Support\Settings\ContentLimits;
use App\Support\Settings\PageSizes;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Опросы статей: настройка вместе со статьёй, голосование, результаты и списки голосовавших.
 */
final class PollService
{
    /**
     * @param PollRepository $polls     опросы и бюллетени
     * @param UserService    $users     карточки проголосовавших
     * @param ContentLimits  $limits    число проголосовавших в превью
     * @param PageSizes      $pageSizes размер страницы полного списка
     */
    public function __construct(
        private readonly PollRepository $polls,
        private readonly UserService $users,
        private readonly ContentLimits $limits,
        private readonly PageSizes $pageSizes,
    ) {
    }

    /**
     * Создаёт, меняет или удаляет опрос статьи.
     *
     * @throws ValidationException за варианты проголосовали после проверки формы, а их текст изменился
     */
    public function sync(Article $article, ?PollData $data): void
    {
        $poll = $this->pollOf($article);

        if ($data === null) {
            if ($poll !== null) {
                $this->polls->delete($poll);
            }

            return;
        }

        if ($poll === null) {
            $this->polls->save(Poll::create((string) Str::uuid(), $article, $data));

            return;
        }

        if (self::optionsChanged($poll, $data)) {
            if ($this->polls->hasBallots($poll)) {
                throw ValidationException::withMessages(['poll.options' => __('messages.polls.options_locked')]);
            }

            $poll->replaceOptions($data->options);
        }

        $poll->configure($data);
        $this->polls->save($poll);
    }

    /**
     * Опрос статьи для её страницы; null — опроса нет.
     */
    public function forArticle(Article $article, ?User $viewer): ?PollViewData
    {
        $poll = $this->polls->findByArticle($article);

        return $poll === null ? null : $this->present($poll, $viewer);
    }

    /**
     * Опрос для формы правки статьи; null — опроса нет.
     */
    public function settingsOf(Article $article): ?PollSettingsData
    {
        $poll = $this->polls->findByArticle($article);

        return $poll === null ? null : new PollSettingsData($poll->settings(), locked: $this->polls->hasBallots($poll));
    }

    /**
     * Нельзя ли сохранить опрос статьи: за варианты уже голосовали, а их текст меняется.
     * Голоса за изменённый текст варианта оказались бы отданы за другой ответ.
     */
    public function optionsLocked(Article $article, PollData $data): bool
    {
        $poll = $this->pollOf($article);

        return $poll !== null && self::optionsChanged($poll, $data) && $this->polls->hasBallots($poll);
    }

    /**
     * Почему голос не принимается; null — голос допустим.
     *
     * @param Collection<int, int> $optionIds выбранные варианты
     */
    public function voteProblem(Poll $poll, User $user, Collection $optionIds): ?PollProblem
    {
        if ($poll->isClosed(CarbonImmutable::now())) {
            return PollProblem::Closed;
        }

        $chosen = $this->chosen($poll, $optionIds);

        if ($chosen->count() !== $optionIds->unique()->count() || ! $poll->accepts($chosen)) {
            return PollProblem::InvalidChoice;
        }

        $alreadyVoted = $this->polls->ballotOf($poll, $user) !== null && ! $poll->allowsRevote();

        return $alreadyVoted ? PollProblem::AlreadyVoted : null;
    }

    /**
     * Почему голос нельзя отозвать; null — отозвать можно.
     */
    public function retractProblem(Poll $poll): ?PollProblem
    {
        if ($poll->isClosed(CarbonImmutable::now())) {
            return PollProblem::Closed;
        }

        return $poll->allowsRevote() ? null : PollProblem::RevoteDisabled;
    }

    /**
     * Голос или переголосование; выбор проверил VotePollRequest.
     *
     * @param Collection<int, int> $optionIds выбранные варианты
     */
    public function vote(Poll $poll, User $user, Collection $optionIds): PollViewData
    {
        $chosen = $this->chosen($poll, $optionIds);
        $ballot = $this->polls->ballotOf($poll, $user);

        if ($ballot === null) {
            $ballot = PollBallot::cast($poll, $user, $chosen);
        }

        $ballot->choose($chosen);
        $this->polls->save($ballot);

        return $this->present($poll, $user);
    }

    /**
     * Отзыв голоса; что опрос это разрешает, проверил RetractVoteRequest.
     */
    public function retract(Poll $poll, User $user): PollViewData
    {
        $ballot = $this->polls->ballotOf($poll, $user);

        if ($ballot !== null) {
            $this->polls->remove($ballot);
        }

        return $this->present($poll, $user);
    }

    /**
     * Полный список проголосовавших за вариант — только в открытом (неанонимном) опросе.
     *
     * @return PageData<UserCardData>
     *
     * @throws EntityNotFoundException варианта нет в этом опросе
     */
    public function voters(Poll $poll, int $optionId, int $page): PageData
    {
        $option = $poll->option($optionId) ?? throw EntityNotFoundException::fromClassNameAndIdentifier(
            PollOption::class,
            ['id' => (string) $optionId],
        );

        return $this->polls->voters($option, $page, $this->pageSizes->pollVoters())
            ->map(fn (User $user): UserCardData => $this->users->card($user));
    }

    /**
     * Опрос с итогами, последними проголосовавшими и выбором посетителя.
     */
    public function present(Poll $poll, ?User $viewer): PollViewData
    {
        $results = $this->polls->results($poll);
        $recent = $poll->isAnonymous()
            ? collect()
            : $this->polls->recentVoters($poll, $this->limits->pollPreviewVoters());
        $users = $this->polls->usersByIds($recent->flatten());
        $ballot = $viewer === null ? null : $this->polls->ballotOf($poll, $viewer);
        $closed = $poll->isClosed(CarbonImmutable::now());
        // Голосовать может вошедший пользователь без блокировки: опрос открыт, статья опубликована,
        // а повторный голос возможен только при переголосовании.
        $canVote = $viewer !== null && ! $viewer->isBanned() && ! $closed && $poll->getArticle()->isPublished()
            && ($ballot === null || $poll->allowsRevote());

        return new PollViewData(
            id: $poll->getUuid(),
            question: $poll->getQuestion(),
            multiple: $poll->isMultiple(),
            maxChoices: $poll->choiceLimit(),
            endsAt: $poll->getEndsAt(),
            closed: $closed,
            allowRevote: $poll->allowsRevote(),
            anonymous: $poll->isAnonymous(),
            totalVoters: $results->voters,
            options: $poll->getOptions()->map(
                fn (PollOption $option): PollOptionViewData => $this->option($option, $results, $recent, $users),
            ),
            myChoice: $ballot?->getOptions()->map(static fn (PollOption $option): int => $option->persistedId()),
            canVote: $canVote,
        );
    }

    /**
     * Варианты опроса из выбранных идентификаторов; чужие идентификаторы отбрасываются.
     *
     * @param Collection<int, int> $optionIds
     *
     * @return Collection<int, PollOption>
     */
    private function chosen(Poll $poll, Collection $optionIds): Collection
    {
        return $poll->getOptions()
            ->filter(static fn (PollOption $option): bool => $optionIds->contains($option->getId()))
            ->values();
    }

    /**
     * Изменился ли текст или состав вариантов.
     */
    private static function optionsChanged(Poll $poll, PollData $data): bool
    {
        return $poll->settings()->options->values()->all() !== $data->options->values()->all();
    }

    /**
     * Вариант с итогами и последними проголосовавшими.
     *
     * @param Collection<int, Collection<int, int>> $recent последние проголосовавшие по варианту
     * @param Collection<int, User>                 $users  пользователи по идентификатору
     */
    private function option(
        PollOption $option,
        PollResultsData $results,
        Collection $recent,
        Collection $users,
    ): PollOptionViewData {
        $id = $option->persistedId();
        $votes = $results->votesFor($id);
        $preview = $recent->get($id, collect())
            ->map(static fn (int $userId): ?User => $users->get($userId))
            ->filter()
            ->values();

        return new PollOptionViewData(
            id: $id,
            text: $option->getText(),
            votes: $votes,
            percent: self::percent($votes, $results->voters),
            voters: $preview->map(fn (User $user): UserCardData => $this->users->card($user)),
            more: max(0, $votes - $preview->count()),
        );
    }

    /**
     * Доля голосов за вариант в процентах от числа проголосовавших; без голосов — ноль.
     */
    private static function percent(int $votes, int $voters): int
    {
        return $voters === 0 ? 0 : (int) round($votes / $voters * 100);
    }

    /**
     * Опрос статьи из базы. У статьи без id опроса быть не может: она ещё не сохранена.
     */
    private function pollOf(Article $article): ?Poll
    {
        return $article->getId() === null ? null : $this->polls->findByArticle($article);
    }
}
