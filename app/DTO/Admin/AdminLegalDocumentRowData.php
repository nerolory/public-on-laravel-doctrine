<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Строка таблицы правовых документов: вид документа, регион, версия и адрес правки.
 */
final readonly class AdminLegalDocumentRowData
{
    /**
     * @param string $typeLabel    название вида документа
     * @param string $regionLabel  название региона или «для всех регионов»
     * @param string $versionLabel текущая версия с датой или подпись отсутствия
     * @param string $editUrl      адрес формы новой версии
     */
    public function __construct(
        public string $typeLabel,
        public string $regionLabel,
        public string $versionLabel,
        public string $editUrl,
    ) {
    }
}
