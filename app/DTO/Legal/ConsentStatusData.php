<?php

declare(strict_types=1);

namespace App\DTO\Legal;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Состояние согласия пользователя для страницы подписок.
 *
 * @implements Arrayable<string, int|string|bool>
 */
final readonly class ConsentStatusData implements Arrayable
{
    /**
     * @param LegalDocumentData $document текущая версия документа
     * @param bool              $agreed   последнее решение: согласие
     * @param bool              $current  согласие дано на текущую версию
     */
    public function __construct(
        public LegalDocumentData $document,
        public bool $agreed,
        public bool $current,
    ) {
    }

    /**
     * Состояние для ответа API.
     *
     * @return array{
     *     type: string,
     *     title: string,
     *     version: int,
     *     required: bool,
     *     agreed: bool,
     *     current: bool,
     *     revocable: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->document->type->value,
            'title' => $this->document->title,
            'version' => $this->document->version,
            'required' => $this->document->required,
            'agreed' => $this->agreed,
            'current' => $this->current,
            'revocable' => $this->document->type->revocableInSettings(),
        ];
    }
}
