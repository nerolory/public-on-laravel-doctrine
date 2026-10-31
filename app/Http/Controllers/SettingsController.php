<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Admin\AdminPage;
use App\Enums\Settings\SettingGroup;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Jobs\Seo\GenerateSitemapJob;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

/**
 * Настройки сайта в админке: сохранение групп, пересборка карты сайта и проверочное письмо.
 */
final class SettingsController extends Controller
{
    /**
     * @param ResponseFactory $responses  ответы
     * @param Redirector      $redirector перенаправления
     * @param SettingsService $settings   настройки сайта
     * @param Dispatcher      $bus        очередь задач
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly SettingsService $settings,
        private readonly Dispatcher $bus,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Сохраняет группу настроек.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->toData());

        return $this->backToAdminPage(
            AdminPage::SiteSettings,
            __('settings.saved'),
            ['group' => $request->group()->value],
        );
    }

    /**
     * Ставит пересборку карты сайта в очередь.
     */
    public function regenerateSitemap(): RedirectResponse
    {
        $this->bus->dispatch(new GenerateSitemapJob());

        return $this->backToAdminPage(
            AdminPage::SiteSettings,
            __('settings.sitemap.queued'),
            ['group' => SettingGroup::Seo->value],
        );
    }

    /**
     * Отправляет проверочное письмо текущему администратору с сохранёнными настройками почты.
     */
    public function testMail(Request $request): RedirectResponse
    {
        $result = $this->settings->sendTestMail($request->user());

        if ($result->failed) {
            return $this->adminPage(AdminPage::SiteSettings, ['group' => SettingGroup::Mail->value])
                ->withErrors(['mail' => $result->message]);
        }

        return $this->backToAdminPage(
            AdminPage::SiteSettings,
            $result->message,
            ['group' => SettingGroup::Mail->value],
        );
    }
}
