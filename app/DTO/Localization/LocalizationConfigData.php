<?php

declare(strict_types=1);

namespace App\DTO\Localization;

use App\Enums\Localization\LocalizationMode;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Режим мультиязычности, язык по умолчанию и включённые языки.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class LocalizationConfigData implements Arrayable
{
    /**
     * @param LocalizationMode            $mode       режим; при одном языке всегда single
     * @param string|null                 $baseDomain основной домен для поддоменов
     * @param string                      $default    код языка по умолчанию
     * @param Collection<int, LocaleData> $languages  включённые, по умолчанию первым
     */
    public function __construct(
        public LocalizationMode $mode,
        public ?string $baseDomain,
        public string $default,
        public Collection $languages,
    ) {
    }

    /**
     * Конфигурация для фронтенда: режим, домен, языки.
     *
     * @return array{
     *     mode: string,
     *     base_domain: ?string,
     *     default: string,
     *     languages: list<array{code: string, name: string}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'base_domain' => $this->baseDomain,
            'default' => $this->default,
            'languages' => $this->languages
                ->map(static fn (LocaleData $language): array => ['code' => $language->code, 'name' => $language->name])
                ->all(),
        ];
    }
}
