<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\SeoMeta;

/**
 * SEO-поля формы: заголовок и описание страницы, оба необязательны.
 */
trait AcceptsSeo
{
    /**
     * SEO-поля из проверенных данных.
     */
    public function seoMeta(): SeoMeta
    {
        return new SeoMeta($this->validated('seo_title'), $this->validated('seo_description'));
    }

    /**
     * Правила SEO-полей.
     *
     * @return array<string, list<string>>
     */
    protected function seoRules(): array
    {
        return [
            'seo_title' => ['nullable', 'string', 'max:' . SeoMeta::TITLE_LENGTH],
            'seo_description' => ['nullable', 'string', 'max:' . SeoMeta::DESCRIPTION_LENGTH],
        ];
    }
}
