<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Contracts\RegistrationPolicy;
use App\DTO\Legal\ConsentStateData;
use App\DTO\Legal\ConsentStatusData;
use App\DTO\Legal\LegalDocumentData;
use App\DTO\Legal\LegalTermsData;
use App\Entities\Consent;
use App\Entities\User;
use App\Enums\Legal\ConsentType;
use App\Exceptions\ConsentNotRevocableException;
use App\Repositories\ConsentRepository;
use App\Repositories\LegalDocumentRepository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Согласия пользователя по правилам выбранного региона.
 * Текущие документы кешируются до изменения документов или настроек ({@see \App\Doctrine\CacheInvalidationSubscriber}).
 */
final class ConsentService
{
    /** Ключ кеша текущих документов по регионам. */
    public const string CACHE_KEY = 'legal.documents';

    /**
     * @param RegistrationPolicies    $policies  правила регионов
     * @param LegalDocumentRepository $documents версии документов
     * @param ConsentRepository       $consents  журнал согласий
     * @param Cache                   $cache     кеш приложения
     */
    public function __construct(
        private readonly RegistrationPolicies $policies,
        private readonly LegalDocumentRepository $documents,
        private readonly ConsentRepository $consents,
        private readonly Cache $cache,
    ) {
    }

    /**
     * Правила текущего региона.
     */
    public function policy(): RegistrationPolicy
    {
        return $this->policies->current();
    }

    /**
     * Условия регистрации для публичной части: регион, возраст и тексты документов.
     */
    public function terms(): LegalTermsData
    {
        $policy = $this->policy();

        return new LegalTermsData(
            region: $policy->region(),
            minimumAge: $policy->minimumAge(),
            cookieBanner: $policy->requiresCookieConsent(),
            dataExport: $policy->allowsDataExport(),
            documents: $this->currentDocuments()->values(),
        );
    }

    /**
     * Виды обязательных согласий, для которых опубликован текст.
     *
     * @return Collection<int, ConsentType>
     */
    public function requiredTypes(): Collection
    {
        return $this->currentDocuments()
            ->filter(static fn (LegalDocumentData $document): bool => $document->required)
            ->map(static fn (LegalDocumentData $document): ConsentType => $document->type)
            ->values();
    }

    /**
     * Значения видов согласий, документы которых опубликованы в регионе: только их можно принять или отозвать.
     *
     * @return Collection<int, string>
     */
    public function availableTypes(): Collection
    {
        return $this->currentDocuments()->keys()->values();
    }

    /**
     * Обязательные документы, текущую версию которых пользователь не принял.
     *
     * @return Collection<int, LegalDocumentData>
     */
    public function pending(User $user): Collection
    {
        return self::pendingOf($this->status($user));
    }

    /**
     * Состояние согласий и документы, которые нужно принять, для страницы подписок.
     */
    public function state(User $user): ConsentStateData
    {
        $items = $this->status($user);

        return new ConsentStateData(items: $items, pending: self::pendingOf($items));
    }

    /**
     * Состояние каждого согласия региона для страницы подписок.
     *
     * @return Collection<int, ConsentStatusData>
     */
    public function status(User $user): Collection
    {
        $latest = $this->consents->latestByType($user);

        return $this->currentDocuments()
            ->map(static fn (LegalDocumentData $document): ConsentStatusData => self::statusOf($document, $latest))
            ->values();
    }

    /**
     * Принимает и отзывает согласия одним действием; возвращает состояние согласий после изменения.
     *
     * @param Collection<int, ConsentType> $accepted
     * @param Collection<int, ConsentType> $revoked
     *
     * @throws ConsentNotRevocableException согласие отзывается только удалением учётной записи
     */
    public function update(
        User $user,
        Collection $accepted,
        Collection $revoked,
        ?string $ip,
        ?string $userAgent,
    ): ConsentStateData {
        $this->accept($user, $accepted, $ip, $userAgent);
        $this->revoke($user, $revoked, $ip, $userAgent);

        return $this->state($user);
    }

    /**
     * Записывает согласия на текущие версии документов.
     *
     * @param Collection<int, ConsentType> $types
     */
    public function accept(User $user, Collection $types, ?string $ip, ?string $userAgent): void
    {
        $this->record($user, $types, agreed: true, ip: $ip, userAgent: $userAgent);
    }

    /**
     * Отзыв согласий. ПДн и правила сайта отзываются только при удалении учётной записи ($deletingAccount).
     *
     * @param Collection<int, ConsentType> $types
     *
     * @throws ConsentNotRevocableException согласие отзывается только удалением учётной записи
     */
    public function revoke(
        User $user,
        Collection $types,
        ?string $ip,
        ?string $userAgent,
        bool $deletingAccount = false,
    ): void {
        $hasNonRevocable = $types->contains(static fn (ConsentType $type): bool => ! $type->revocableInSettings());

        if (! $deletingAccount && $hasNonRevocable) {
            throw new ConsentNotRevocableException();
        }

        $this->record($user, $types, agreed: false, ip: $ip, userAgent: $userAgent);
    }

    /**
     * Обязательные документы, текущую версию которых не приняли, из состояний согласий.
     *
     * @param Collection<int, ConsentStatusData> $statuses
     *
     * @return Collection<int, LegalDocumentData>
     */
    private static function pendingOf(Collection $statuses): Collection
    {
        return $statuses
            ->filter(static fn (ConsentStatusData $status): bool => $status->document->required && ! $status->current)
            ->map(static fn (ConsentStatusData $status): LegalDocumentData => $status->document)
            ->values();
    }

    /**
     * Состояние одного согласия по последней записи пользователя.
     *
     * @param Collection<string, Consent> $latest последние записи по виду согласия
     */
    private static function statusOf(LegalDocumentData $document, Collection $latest): ConsentStatusData
    {
        $consent = $latest->get($document->type->value);
        $agreed = $consent !== null && $consent->isAgreed();

        return new ConsentStatusData(
            document: $document,
            agreed: $agreed,
            current: $agreed && $consent->getDocument()->getId() === $document->id,
        );
    }

    /**
     * @param Collection<int, ConsentType> $types
     */
    private function record(User $user, Collection $types, bool $agreed, ?string $ip, ?string $userAgent): void
    {
        $documents = $this->documents->current($types, $this->policy()->region());
        // Виды согласий проверены запросом по документам региона, поэтому документ есть у каждого.
        $records = $types->map(function (ConsentType $type) use ($user, $documents, $agreed, $ip, $userAgent): Consent {
            $document = $documents->get($type->value)
                ?? throw new LogicException('No current document for consent ' . $type->value);

            return Consent::record($user, $document, agreed: $agreed, ipAddress: $ip, userAgent: $userAgent);
        });

        $this->consents->add($records->values());
    }

    /**
     * Текущие документы региона в порядке политики: сначала обязательные согласия, затем добровольные.
     *
     * @return Collection<string, LegalDocumentData> по значению ConsentType
     */
    private function currentDocuments(): Collection
    {
        // Документы всех регионов кешируются вместе: смена региона в настройках не требует нового запроса.
        $byRegion = $this->cache->rememberForever(self::CACHE_KEY, fn (): Collection => $this->policies->all()
            ->map(fn (RegistrationPolicy $policy): Collection => $this->documentsOf($policy)));

        return $byRegion->get($this->policy()->region());
    }

    /**
     * Текущие версии документов региона по значению ConsentType.
     *
     * @return Collection<string, LegalDocumentData>
     */
    private function documentsOf(RegistrationPolicy $policy): Collection
    {
        $required = $policy->requiredConsents();
        $types = $required->merge($policy->optionalConsents());
        $current = $this->documents->current($types, $policy->region());
        $documents = $types
            ->filter(static fn (ConsentType $type): bool => $current->has($type->value))
            ->mapWithKeys(static fn (ConsentType $type): array => [
                $type->value => LegalDocumentData::fromEntity(
                    $current->get($type->value),
                    required: $required->contains($type),
                ),
            ]);

        return $documents;
    }
}
