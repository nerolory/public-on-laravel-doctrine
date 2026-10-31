<?php

declare(strict_types=1);

namespace App\DTO\Legal;

use App\Entities\LegalDocument;
use App\Enums\Legal\ConsentType;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Текущая версия документа региона и обязательность согласия.
 *
 * @implements Arrayable<string, int|string|bool>
 */
final readonly class LegalDocumentData implements Arrayable
{
    /**
     * @param int         $id       идентификатор версии
     * @param ConsentType $type     вид согласия
     * @param int         $version  номер версии
     * @param string      $title    заголовок
     * @param string      $text     текст
     * @param bool        $required без него закрыты регистрация и публикация
     */
    public function __construct(
        public int $id,
        public ConsentType $type,
        public int $version,
        public string $title,
        public string $text,
        public bool $required,
    ) {
    }

    /**
     * Данные версии документа.
     */
    public static function fromEntity(LegalDocument $document, bool $required): self
    {
        return new self(
            id: $document->persistedId(),
            type: $document->getType(),
            version: $document->getVersion(),
            title: $document->getTitle(),
            text: $document->getText(),
            required: $required,
        );
    }

    /**
     * Документ для ответа API.
     *
     * @return array{id: int, type: string, version: int, title: string, text: string, required: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'version' => $this->version,
            'title' => $this->title,
            'text' => $this->text,
            'required' => $this->required,
        ];
    }
}
