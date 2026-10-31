<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\DTO\Common\PageData;
use App\DTO\Localization\LanguageData;
use App\Entities\Language;
use App\Repositories\LanguageRepository;

/**
 * Управление языками сайта в админке.
 */
final class LanguageService
{
    /**
     * @param LanguageRepository $languages языки
     */
    public function __construct(
        private readonly LanguageRepository $languages,
    ) {
    }

    /**
     * Страница языков: язык по умолчанию первым.
     *
     * @return PageData<Language>
     */
    public function page(int $page, int $limit): PageData
    {
        return $this->languages->page($page, $limit);
    }

    /**
     * Есть ли язык с таким кодом.
     */
    public function exists(string $code): bool
    {
        return $this->languages->find($code) !== null;
    }

    /**
     * Язык по коду или null.
     */
    public function find(string $code): ?Language
    {
        return $this->languages->find($code);
    }

    /**
     * Новый язык; первый язык сайта сразу становится языком по умолчанию.
     */
    public function create(LanguageData $data): Language
    {
        $language = Language::create(
            $data->code,
            $data->name,
            $data->file,
            enabled: $data->enabled || $data->isDefault,
        );
        $this->languages->save($language);

        if ($data->isDefault || $this->languages->findDefault() === null) {
            $this->languages->switchDefault($language);
        }

        return $language;
    }

    /**
     * Меняет язык; язык по умолчанию остаётся включённым.
     */
    public function update(Language $language, LanguageData $data): void
    {
        if ($data->isDefault && ! $language->isDefault()) {
            $this->languages->switchDefault($language);
        }

        $language->update($data->name, $data->file, enabled: $data->enabled || $language->isDefault());
        $this->languages->save($language);
    }

    /**
     * Удаляет язык; что это не язык по умолчанию, проверил DeleteLanguageRequest.
     */
    public function delete(Language $language): void
    {
        $this->languages->delete($language);
    }
}
