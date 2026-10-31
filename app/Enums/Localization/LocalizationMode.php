<?php

declare(strict_types=1);

namespace App\Enums\Localization;

use App\Traits\ProvidesEnumValues;
use LogicException;

/**
 * Режим мультиязычности: один язык, префикс языка в адресе (/en/) или поддомен (en.example.com).
 */
enum LocalizationMode: string
{
    use ProvidesEnumValues;
    /** Один язык: адреса без префикса и поддомена. */
    case Single = 'single';

    /** Язык первым сегментом пути, например /en/. */
    case Prefix = 'prefix';

    /** Язык поддоменом, например en.example.com. */
    case Subdomain = 'subdomain';

    /**
     * Есть ли несколько языков: префикс и поддомен допускают языки кроме языка по умолчанию.
     */
    public function isMultilingual(): bool
    {
        return $this !== self::Single;
    }

    /**
     * Абсолютный адрес страницы на языке, отличном от языка по умолчанию.
     * В режиме поддоменов основной домен обязателен: его требует проверка настроек.
     */
    public function localize(string $baseUrl, string $path, string $language, ?string $baseDomain): string
    {
        return match ($this) {
            self::Single => $baseUrl . $path,
            self::Prefix => $baseUrl . '/' . $language . ($path === '/' ? '' : $path),
            self::Subdomain => $this->subdomainUrl($baseUrl, $path, $language, $baseDomain),
        };
    }

    /**
     * Адрес на поддомене языка: схема и порт берутся из адреса сайта.
     */
    private function subdomainUrl(string $baseUrl, string $path, string $language, ?string $baseDomain): string
    {
        if ($baseDomain === null) {
            throw new LogicException('The subdomain mode requires the base domain setting.');
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        $portPart = $port === null ? '' : ':' . $port;

        return $scheme . '://' . $language . '.' . $baseDomain . $portPart . $path;
    }
}
