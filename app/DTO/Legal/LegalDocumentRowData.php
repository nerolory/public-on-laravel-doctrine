<?php

declare(strict_types=1);

namespace App\DTO\Legal;

use App\Entities\LegalDocument;
use App\Enums\Legal\ConsentType;

/**
 * Строка таблицы правовых документов в админке: вид документа, регион и его последняя версия.
 */
final readonly class LegalDocumentRowData
{
    /**
     * @param ConsentType        $type     вид документа
     * @param string|null        $region   регион; null — документ для всех регионов
     * @param LegalDocument|null $document последняя версия; null — документ ещё не опубликован
     */
    public function __construct(
        public ConsentType $type,
        public ?string $region,
        public ?LegalDocument $document,
    ) {
    }
}
