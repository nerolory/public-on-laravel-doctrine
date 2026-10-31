<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\DTO\Common\PageData;
use App\DTO\Legal\LegalDocumentRowData;
use App\Entities\LegalDocument;
use App\Enums\Legal\ConsentType;
use App\Repositories\LegalDocumentRepository;
use Illuminate\Support\Collection;

/**
 * Публикация новых версий правовых документов из админки.
 */
final class LegalDocumentService
{
    /**
     * @param LegalDocumentRepository $documents версии документов
     * @param RegistrationPolicies    $policies  правила регионов
     */
    public function __construct(
        private readonly LegalDocumentRepository $documents,
        private readonly RegistrationPolicies $policies,
    ) {
    }

    /**
     * Публикует новую версию. Если текст и заголовок не изменились, возвращается прежняя версия:
     * повторное согласие без изменений в тексте пользователям не нужно.
     */
    public function publish(ConsentType $type, ?string $region, string $title, string $text): LegalDocument
    {
        $latest = $this->documents->findLatest($type, $region);

        if ($latest !== null && $latest->getTitle() === $title && $latest->getText() === $text) {
            return $latest;
        }

        $document = LegalDocument::publish($type, $region, ($latest?->getVersion() ?? 0) + 1, $title, $text);
        $this->documents->save($document);

        return $document;
    }

    /**
     * Последняя версия документа вида для региона; null в регионе — общий документ.
     */
    public function latest(ConsentType $type, ?string $region): ?LegalDocument
    {
        return $this->documents->findLatest($type, $region);
    }

    /**
     * Строки таблицы в админке: каждый вид в каждом регионе с последней версией, если она есть; по страницам.
     *
     * @return PageData<LegalDocumentRowData>
     */
    public function overview(int $page, int $limit): PageData
    {
        $latest = $this->documents->latestPerPair();
        $regions = $this->policies->regions()->prepend(value: null);
        $rows = collect(ConsentType::cases())
            ->crossJoin($regions->all())
            ->map(static fn (array $pair): LegalDocumentRowData => new LegalDocumentRowData(
                type: $pair[0],
                region: $pair[1],
                document: $latest->get(LegalDocumentRepository::pairKey($pair[0], $pair[1])),
            ));

        return new PageData($rows->forPage($page, $limit)->values(), total: $rows->count());
    }

    /**
     * Ключи всех регионов.
     *
     * @return Collection<int, string>
     */
    public function regions(): Collection
    {
        return $this->policies->regions();
    }

    /**
     * Регион из настроек сайта.
     */
    public function currentRegion(): string
    {
        return $this->policies->current()->region();
    }
}
