<?php

declare(strict_types=1);

namespace App\DTO\Seo;

use Illuminate\Support\Collection;
use Spatie\SchemaOrg\BaseType;

/**
 * Исходные данные страницы для тегов <head>.
 * Заголовки, описание, картинка и разметка Schema.org.
 */
final readonly class PageSeoData
{
    /**
     * @param string                       $title       заголовок страницы
     * @param string                       $path        путь от корня сайта
     * @param string|null                  $description описание; null или пустое не выводится
     * @param string                       $ogType      тип Open Graph: website или article
     * @param string|null                  $image       картинка для соцсетей
     * @param Collection<int, BaseType>    $jsonLd      разметка Schema.org
     * @param string|null                  $ogTitle     заголовок для соцсетей
     * @param Collection<int, MetaTagData> $meta        теги meta после описания
     * @param Collection<int, MetaTagData> $ogTags      теги и разделы статьи
     * @param bool                         $noindex     закрыть от индексации
     */
    public function __construct(
        public string $title,
        public string $path,
        public ?string $description,
        public string $ogType,
        public ?string $image,
        public Collection $jsonLd,
        public ?string $ogTitle = null,
        public Collection $meta = new Collection(),
        public Collection $ogTags = new Collection(),
        public bool $noindex = false,
    ) {
    }
}
