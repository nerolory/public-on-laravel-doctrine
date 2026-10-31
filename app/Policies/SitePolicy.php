<?php

declare(strict_types=1);

namespace App\Policies;

use App\Entities\User;
use App\Enums\Social\SocialProvider;
use App\Services\Legal\ConsentService;
use App\Services\Seo\PublicFilesService;
use App\Services\Social\SocialProviders;
use App\Services\User\PermissionService;
use Illuminate\Auth\Access\Response;

/**
 * Права, не привязанные к записи: разделы админки и возможности сайта, включаемые в настройках.
 * Выключенная возможность для посетителя не существует: ответ 404.
 */
final class SitePolicy
{
    /**
     * @param PermissionService  $permissions роли пользователя
     * @param PublicFilesService $files       RSS-лента
     * @param ConsentService     $consents    правила правового региона
     * @param SocialProviders    $providers   провайдеры входа
     */
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly PublicFilesService $files,
        private readonly ConsentService $consents,
        private readonly SocialProviders $providers,
    ) {
    }

    /**
     * Вход в админку: модераторы, администраторы и владелец.
     */
    public function accessAdmin(User $user): bool
    {
        return $this->permissions->canModerate($user);
    }

    /**
     * Управление пользователями: администраторы и владелец.
     */
    public function manageUsers(User $user): bool
    {
        return $this->permissions->canManageUsers($user);
    }

    /**
     * Настройки сайта: администраторы и владелец.
     */
    public function manageSettings(User $user): bool
    {
        return $this->permissions->canManageSettings($user);
    }

    /**
     * RSS-лента включена.
     */
    public function readFeed(?User $user): Response
    {
        return $this->files->feedEnabled() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Выгрузка своих данных предусмотрена правилами региона.
     */
    public function exportData(User $user): Response
    {
        return $this->consents->policy()->allowsDataExport() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Вход через включённого OAuth-провайдера; значение из адреса ограничено вариантами SocialProvider.
     */
    public function oauthLogin(?User $user, string $provider): Response
    {
        return $this->providers->isOAuthEnabled(SocialProvider::from($provider))
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Вход через Telegram включён.
     */
    public function telegramLogin(?User $user): Response
    {
        return $this->providers->isEnabled(SocialProvider::Telegram) ? Response::allow() : Response::denyAsNotFound();
    }
}
