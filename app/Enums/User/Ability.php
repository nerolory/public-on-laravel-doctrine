<?php

declare(strict_types=1);

namespace App\Enums\User;

/**
 * Права, не привязанные к конкретной записи: проверяются через Gate и middleware can (SitePolicy).
 */
enum Ability: string
{
    /** Вход в админку: модерация публикаций и жалоб. */
    case AccessAdmin = 'access-admin';

    /** Управление пользователями, статистика и рейтинг конфликтности. */
    case ManageUsers = 'manage-users';

    /** Настройки сайта, языки, правовые документы и аналитика. */
    case ManageSettings = 'manage-settings';

    /** RSS-лента включена в настройках. */
    case ReadFeed = 'read-feed';

    /** Выгрузка своих данных предусмотрена правилами региона. */
    case ExportData = 'export-data';

    /** Вход через OAuth-провайдера из адреса. */
    case OAuthLogin = 'oauth-login';

    /** Вход через Telegram. */
    case TelegramLogin = 'telegram-login';
}
