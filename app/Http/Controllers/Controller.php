<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Admin\AdminPage;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Базовый класс HTTP-контроллеров приложения.
 */
abstract class Controller
{
    /**
     * @param ResponseFactory $responses  ответы с телом и заголовками
     * @param Redirector      $redirector перенаправления
     */
    public function __construct(
        protected readonly ResponseFactory $responses,
        protected readonly Redirector $redirector,
    ) {
    }

    /**
     * Перенаправление на страницу админки MoonShine.
     *
     * @param array<string, string> $parameters дополнительные параметры адреса страницы
     */
    protected function adminPage(AdminPage $page, array $parameters = []): RedirectResponse
    {
        return $this->redirector->route('moonshine.page', ['pageUri' => $page->value, ...$parameters]);
    }

    /**
     * Перенаправление на страницу админки MoonShine с сообщением об успехе.
     *
     * @param array<string, string> $parameters дополнительные параметры адреса страницы
     */
    protected function backToAdminPage(AdminPage $page, string $status, array $parameters = []): RedirectResponse
    {
        return $this->adminPage($page, $parameters)->with('status', $status);
    }

    /**
     * Возврат на предыдущую страницу с сообщением об успехе.
     */
    protected function backWithStatus(string $status): RedirectResponse
    {
        return $this->redirector->back()->with('status', $status);
    }
}
