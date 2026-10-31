<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Внешние сервисы
    |--------------------------------------------------------------------------
    |
    | Неизменные адреса сторонних сервисов. Ключи доступа и включение
    | сервисов хранятся в настройках сайта в базе данных.
    |
    */

    'yandex_metrika' => [
        'stat_url' => 'https://api-metrika.yandex.net/stat/v1/data',
    ],

    // Вход через Telegram: страница авторизации.
    'telegram_login' => [
        'auth_url' => 'https://oauth.telegram.org/auth',
    ],

    // Шаблоны ссылок «поделиться»: {url} и {title} подставляются закодированными.
    'share' => [
        'vk' => 'https://vk.com/share.php?url={url}&title={title}',
        'ok' => 'https://connect.ok.ru/offer?url={url}&title={title}',
        'telegram' => 'https://t.me/share/url?url={url}&text={title}',
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
        'x' => 'https://twitter.com/intent/tweet?url={url}&text={title}',
    ],

    // Скрипты счётчиков: {id} — идентификатор счётчика из настроек.
    'counters' => [
        'yandex' => 'https://mc.yandex.ru/metrika/tag.js',
        'ga4' => 'https://www.googletagmanager.com/gtag/js?id={id}',
        'gtm' => 'https://www.googletagmanager.com/gtm.js?id={id}',
        'vk' => 'https://top-fwz1.mail.ru/js/code.js',
        'meta' => 'https://connect.facebook.net/en_US/fbevents.js',
    ],

];
