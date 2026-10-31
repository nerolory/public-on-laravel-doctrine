<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Image;
use App\Enums\Media\StorageDriver;
use App\Enums\Settings\SettingGroup;
use App\Support\Media\MediaStorage;
use Illuminate\Http\UploadedFile;
use Tests\DoctrineTestCase;

/**
 * Загрузка картинок: конвертация в webp, ширины, отсутствие увеличения, проверка файла и хранилище S3.
 */
final class ImageUploadTest extends DoctrineTestCase
{
    /**
     * Большая картинка сохраняется в webp нескольких ширин, размеры и srcset соответствуют им.
     */
    public function test_uploaded_image_is_converted_to_webp_in_several_widths(): void
    {
        $this->actingAs($this->createUser());
        $response = $this->post(
            '/api/images',
            ['file' => UploadedFile::fake()->image('photo.jpg', 2000, 1000), 'purpose' => 'content'],
            ['Accept' => 'application/json'],
        )->assertCreated()->assertJsonStructure(['id', 'url', 'srcset', 'width', 'height']);

        $uuid = $response->json('id');
        self::assertMatchesRegularExpression(
            '#^/media/content/\d{4}/\d{2}/' . $uuid . '/1920\.webp$#',
            $response->json('url'),
        );
        self::assertSame([1920, 960], [$response->json('width'), $response->json('height')]);
        self::assertSame(5, substr_count($response->json('srcset'), '.webp '));

        $image = $this->em->getRepository(Image::class)->findOneBy(['uuid' => $uuid]);
        self::assertSame([320, 640, 960, 1280, 1920], $image->getWidths()->all());
        self::assertSame(
            2000,
            (int) $this->em->getConnection()->fetchOne('SELECT width FROM images WHERE uuid = ?', [$uuid]),
        );
        foreach ($image->getWidths() as $width) {
            $path = $this->mediaRoot . '/' . $image->pathFor($width);
            self::assertFileExists($path);
            self::assertSame('image/webp', mime_content_type($path));
            self::assertSame($width, getimagesize($path)[0]);
        }
    }

    /**
     * Маленькая картинка сохраняется без увеличения; обложка получает свой размер.
     */
    public function test_small_image_is_not_upscaled_and_cover_uses_own_sizes(): void
    {
        $this->actingAs($this->createUser());
        $this->postJson('/api/images', [
            'file' => UploadedFile::fake()->image('small.png', 200, 100), 'purpose' => 'content',
        ])
            ->assertCreated()
            ->assertJsonPath('width', 200)
            ->assertJsonPath('height', 100);
        $this->postJson('/api/images', [
            'file' => UploadedFile::fake()->image('cover.jpg', 1000, 500), 'purpose' => 'cover',
        ])
            ->assertCreated()
            ->assertJsonPath('width', 960);
    }

    /**
     * Загружать может только вошедший и не заблокированный пользователь; формат и назначение проверяются.
     */
    public function test_upload_is_validated_and_requires_an_active_user(): void
    {
        $this->postJson('/api/images', ['file' => UploadedFile::fake()->image('photo.jpg'), 'purpose' => 'content'])
            ->assertUnauthorized();

        $user = $this->createUser();
        $this->actingAs($user);
        $this->postJson('/api/images', [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'), 'purpose' => 'content',
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/api/images', ['file' => UploadedFile::fake()->image('photo.jpg'), 'purpose' => 'avatar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purpose');

        $user->ban();
        $this->em->flush();
        $this->postJson('/api/images', ['file' => UploadedFile::fake()->image('photo.jpg'), 'purpose' => 'content'])
            ->assertForbidden();
    }

    /**
     * Диск S3 собирается из настроек хранилища, адрес файла строится от публичного адреса.
     */
    public function test_s3_disk_is_built_from_settings(): void
    {
        $this->updateSettings(SettingGroup::Storage, [
            'storage__driver' => 's3', 'storage__s3__key' => 'key', 'storage__s3__secret' => 'secret',
            'storage__s3__region' => 'ru-central1', 'storage__s3__bucket' => 'media',
            'storage__s3__endpoint' => 'https://storage.example.test', 'storage__s3__url' => 'https://cdn.example.test',
            'storage__s3__path_style' => '1',
        ]);
        $storage = $this->app->make(MediaStorage::class);

        self::assertSame(StorageDriver::S3, $storage->currentDriver());
        self::assertSame('https://cdn.example.test/content/a.webp', $storage->url(StorageDriver::S3, 'content/a.webp'));
    }
}
