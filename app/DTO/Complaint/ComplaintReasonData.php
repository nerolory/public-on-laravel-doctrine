<?php

declare(strict_types=1);

namespace App\DTO\Complaint;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Причина жалобы для формы: код и признак обязательного текста.
 *
 * @implements Arrayable<string, string|bool>
 */
final readonly class ComplaintReasonData implements Arrayable
{
    /**
     * @param string $value        код причины
     * @param bool   $requiresText нужен ли текст жалобы
     */
    public function __construct(
        public string $value,
        public bool $requiresText,
    ) {
    }

    /**
     * Причина для ответа API.
     *
     * @return array{value: string, requires_text: bool}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'requires_text' => $this->requiresText,
        ];
    }
}
