<?php

declare(strict_types=1);

namespace App\DTO\Media;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Параметры S3-совместимого хранилища из настроек сайта.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class S3SettingsData implements Arrayable
{
    /**
     * @param string|null $key       ключ доступа
     * @param string|null $secret    секретный ключ
     * @param string|null $region    регион
     * @param string|null $bucket    бакет
     * @param string|null $endpoint  адрес API хранилища; null — адрес по умолчанию
     * @param string|null $url       публичный адрес файлов; null — адрес по умолчанию
     * @param bool        $pathStyle адрес вида host/bucket
     */
    public function __construct(
        public ?string $key,
        public ?string $secret,
        public ?string $region,
        public ?string $bucket,
        public ?string $endpoint,
        public ?string $url,
        public bool $pathStyle,
    ) {
    }

    /**
     * Параметры S3 в ключах конфига диска Laravel.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'secret' => $this->secret,
            'region' => $this->region,
            'bucket' => $this->bucket,
            'endpoint' => $this->endpoint,
            'url' => $this->url,
            'use_path_style_endpoint' => $this->pathStyle,
        ];
    }
}
