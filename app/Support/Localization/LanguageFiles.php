<?php

declare(strict_types=1);

namespace App\Support\Localization;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

/**
 * Список папок переводов в lang/: новая папка с переводами сразу доступна для выбора в админке.
 */
final class LanguageFiles
{
    /** Имя папки переводов: код языка и необязательный регион, например en или pt_BR. */
    private const string NAME_PATTERN = '/^[a-z]{2}(?:[-_][A-Za-z]{2})?$/';

    /** Папка переводов пакетов, это не язык. */
    private const string VENDOR = 'vendor';

    /**
     * @param Filesystem $files    файловая система
     * @param string     $langPath путь к папке lang/
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $langPath,
    ) {
    }

    /**
     * Папки переводов по алфавиту.
     *
     * @return Collection<int, string>
     */
    public function available(): Collection
    {
        return collect($this->files->directories($this->langPath))
            ->map(static fn (string $path): string => basename($path))
            ->reject(static fn (string $name): bool => $name === self::VENDOR)
            ->filter(static fn (string $name): bool => preg_match(self::NAME_PATTERN, $name) === 1)
            ->sort()
            ->values();
    }
}
