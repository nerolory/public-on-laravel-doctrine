<?php

declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;

/**
 * Язык интерфейса сайта. Код — бизнес-ключ, он же префикс адреса (/en/) или поддомен (en.example.com).
 * Переводы берутся из папки lang/{file}; одну папку могут использовать несколько языков.
 */
#[ORM\Entity]
#[ORM\Table(name: 'languages')]
class Language
{
    /** Код языка: две буквы и необязательный регион, например en или pt-br. */
    public const string CODE_REGEX = '[a-z]{2}(?:-[a-z]{2})?';

    /** Шаблон проверки кода языка. */
    public const string CODE_PATTERN = '/^' . self::CODE_REGEX . '$/';

    /** Длина кода языка — размер колонки code: две буквы, дефис и регион (en-us). */
    public const int CODE_LENGTH = 5;

    /** Длина названия языка — размер колонки name: самоназвания языков короче. */
    public const int NAME_LENGTH = 60;

    /** Длина имени папки переводов в lang/ — размер колонки file: код языка с регионом и запасом. */
    public const int FILE_LENGTH = 40;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: self::CODE_LENGTH)]
    private string $code;

    #[ORM\Column(type: 'string', length: self::NAME_LENGTH)]
    private string $name;

    #[ORM\Column(type: 'string', length: self::FILE_LENGTH)]
    private string $file;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(name: 'is_default', type: 'boolean')]
    private bool $isDefault = false;

    /**
     * Новый язык; код уже проверен формой.
     */
    public static function create(string $code, string $name, string $file, bool $enabled = true): self
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException('Invalid language code: ' . $code);
        }

        $language = new self();
        $language->code = $code;
        $language->update($name, $file, $enabled);

        return $language;
    }

    /**
     * Меняет название, папку переводов и включённость.
     *
     * @throws LogicException язык по умолчанию нельзя выключить
     */
    public function update(string $name, string $file, bool $enabled): void
    {
        if ($this->isDefault && ! $enabled) {
            throw new LogicException('The default language cannot be disabled.');
        }

        $this->name = $name;
        $this->file = $file;
        $this->enabled = $enabled;
    }

    /**
     * Делает язык языком по умолчанию; такой язык всегда включён.
     */
    public function makeDefault(): void
    {
        $this->isDefault = true;
        $this->enabled = true;
    }

    /**
     * Снимает признак языка по умолчанию.
     */
    public function unsetDefault(): void
    {
        $this->isDefault = false;
    }

    /**
     * Код языка.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Название языка в переключателе.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Папка переводов в lang/.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Включён ли язык на сайте.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Язык по умолчанию.
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }
}
