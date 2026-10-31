<?php

declare(strict_types=1);

namespace App\DTO\Complaint;

use App\Enums\Complaint\ComplaintReason;

/**
 * Жалоба читателя из формы на сайте.
 */
final readonly class ComplaintData
{
    /**
     * @param string          $target ключ цели: article, comment, user, album
     * @param string          $id     идентификатор объекта на сайте: число или UUID
     * @param ComplaintReason $reason причина
     * @param string|null     $text   пояснение; для причины «другое» обязательно
     */
    public function __construct(
        public string $target,
        public string $id,
        public ComplaintReason $reason,
        public ?string $text = null,
    ) {
    }
}
