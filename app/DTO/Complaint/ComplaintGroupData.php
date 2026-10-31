<?php

declare(strict_types=1);

namespace App\DTO\Complaint;

use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Открытые жалобы на один объект: число и первая дата.
 * Разбивка по причинам и последние пояснения читателей.
 */
final readonly class ComplaintGroupData
{
    /**
     * @param string                  $target  ключ цели: article, comment, user, album
     * @param int                     $id      идентификатор объекта
     * @param int                     $total   число открытых жалоб
     * @param DateTimeImmutable       $first   время первой жалобы
     * @param Collection<string, int> $reasons число жалоб по значению ComplaintReason
     * @param Collection<int, string> $texts   последние пояснения
     * @param ComplaintLabelData|null $label   подпись и ссылка для очереди
     */
    public function __construct(
        public string $target,
        public int $id,
        public int $total,
        public DateTimeImmutable $first,
        public Collection $reasons,
        public Collection $texts,
        public ?ComplaintLabelData $label = null,
    ) {
    }

    /**
     * Та же группа с подписью объекта.
     */
    public function withLabel(ComplaintLabelData $label): self
    {
        return new self($this->target, $this->id, $this->total, $this->first, $this->reasons, $this->texts, $label);
    }
}
