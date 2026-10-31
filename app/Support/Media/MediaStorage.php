<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\DTO\Media\S3SettingsData;
use App\Enums\Media\StorageDriver;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;

/**
 * Диск для медиафайлов, собранный из настроек сайта: папка public/media или S3-совместимое хранилище.
 */
final class MediaStorage
{
    /** @var array<string, Filesystem> собранные диски по значению StorageDriver */
    private array $disks = [];

    /**
     * @param FilesystemManager $filesystems сборка дисков Laravel
     * @param SettingsService   $settings    настройки хранилища
     * @param string            $localRoot   папка локальных файлов
     */
    public function __construct(
        private readonly FilesystemManager $filesystems,
        private readonly SettingsService $settings,
        private readonly string $localRoot,
    ) {
    }

    /**
     * Хранилище для новых файлов по текущей настройке.
     */
    public function currentDriver(): StorageDriver
    {
        return StorageDriver::from($this->settings->string(SettingKey::StorageDriver));
    }

    /**
     * Диск хранилища; собирается один раз на запрос или задачу очереди.
     */
    public function disk(StorageDriver $driver): Filesystem
    {
        return $this->disks[$driver->value] ??= $this->filesystems->build(
            $driver->diskConfig($this->localRoot, $this->s3Settings()),
        );
    }

    /**
     * Публичный адрес файла.
     */
    public function url(StorageDriver $driver, string $path): string
    {
        return $this->disk($driver)->url($path);
    }

    /**
     * Параметры S3 из настроек; незаполненные адреса означают адреса самого хранилища по умолчанию.
     */
    private function s3Settings(): S3SettingsData
    {
        return new S3SettingsData(
            key: $this->settings->optionalString(SettingKey::StorageS3Key),
            secret: $this->settings->optionalString(SettingKey::StorageS3Secret),
            region: $this->settings->optionalString(SettingKey::StorageS3Region),
            bucket: $this->settings->optionalString(SettingKey::StorageS3Bucket),
            endpoint: $this->settings->optionalString(SettingKey::StorageS3Endpoint),
            url: $this->settings->optionalString(SettingKey::StorageS3Url),
            pathStyle: $this->settings->boolean(SettingKey::StorageS3PathStyle),
        );
    }
}
