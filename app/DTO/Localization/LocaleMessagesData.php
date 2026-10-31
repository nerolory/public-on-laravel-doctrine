<?php

declare(strict_types=1);

namespace App\DTO\Localization;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Переводы языка для фронтенда: публичная часть и подписи редактора статей.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class LocaleMessagesData implements Arrayable
{
    /**
     * @param array<string, mixed> $front  переводы публичной части из front.php
     * @param array<string, mixed> $editor подписи редактора статей из editor.php
     */
    public function __construct(
        public array $front,
        public array $editor,
    ) {
    }

    /**
     * Переводы для ответа API: подписи редактора лежат под ключом editor.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->front + ['editor' => $this->editor];
    }
}
