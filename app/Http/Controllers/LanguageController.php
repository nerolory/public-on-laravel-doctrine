<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\Language;
use App\Enums\Admin\AdminPage;
use App\Http\Requests\Localization\DeleteLanguageRequest;
use App\Http\Requests\Localization\StoreLanguageRequest;
use App\Services\Localization\LanguageService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Языки сайта в админке: создание, изменение и удаление.
 */
final class LanguageController extends Controller
{
    /**
     * @param ResponseFactory $responses  ответы
     * @param Redirector      $redirector перенаправления
     * @param LanguageService $languages  языки
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly LanguageService $languages,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Создаёт язык.
     */
    public function store(StoreLanguageRequest $request): RedirectResponse
    {
        $this->languages->create($request->languageData());

        return $this->backToAdminPage(AdminPage::Languages, __('languages.created'));
    }

    /**
     * Меняет язык.
     */
    public function update(Language $language, StoreLanguageRequest $request): RedirectResponse
    {
        $this->languages->update($language, $request->languageData());

        return $this->backToAdminPage(AdminPage::Languages, __('languages.updated'));
    }

    /**
     * Удаляет язык; язык по умолчанию удалить нельзя.
     */
    public function destroy(Language $language, DeleteLanguageRequest $request): RedirectResponse
    {
        $this->languages->delete($language);

        return $this->backToAdminPage(AdminPage::Languages, __('languages.deleted'));
    }
}
