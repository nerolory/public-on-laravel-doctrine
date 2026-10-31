<?php

declare(strict_types=1);

namespace App\Enums\Media;

use App\DTO\Media\S3SettingsData;
use App\Traits\ProvidesEnumValues;

/**
 * Хранилище медиафайлов: папка public/media или S3-совместимое хранилище.
 */
enum StorageDriver: string
{
    use ProvidesEnumValues;

    /** Адрес папки public/media на сайте: её раздаёт nginx. */
    private const string LOCAL_URL = '/media';

    /** Файлы в папке public/media. */
    case Local = 'local';

    /** Файлы в S3-совместимом хранилище. */
    case S3 = 's3';

    /**
     * Параметры диска Laravel. Параметры S3 используются только вариантом S3.
     *
     * @return array<string, mixed>
     */
    public function diskConfig(string $localRoot, S3SettingsData $s3): array
    {
        return match ($this) {
            self::Local => [
                'driver' => 'local',
                'root' => $localRoot,
                'url' => self::LOCAL_URL,
                'visibility' => 'public',
                'throw' => true,
            ],
            self::S3 => [
                'driver' => 's3',
                ...$s3->toArray(),
                'visibility' => 'public',
                'throw' => true,
            ],
        };
    }
}
