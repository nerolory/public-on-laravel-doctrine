<?php

declare(strict_types=1);

namespace App\Enums\Http;

/**
 * Именованные ограничители частоты запросов (middleware throttle:имя): лимит на пользователя, у гостя — на IP.
 */
enum RateLimit: string
{
    /** Чтение и лёгкие действия: лента, комментарии, реакции, просмотры. */
    case Read = 'read';

    /** Загрузки и голоса: картинки, графики, опросы, действия в админке. */
    case Interact = 'interact';

    /** Правки своих данных и вход. */
    case Edit = 'edit';

    /** Создание и удаление записей, регистрация. */
    case Create = 'create';

    /** Тяжёлые операции: выгрузка данных, тестовое письмо, пересборка карты сайта. */
    case Heavy = 'heavy';

    /** Повторная отправка письма подтверждения. */
    case Mail = 'mail';

    /** Запросов в минуту на ключ ограничителя: чем дороже операция, тем меньше. */
    private const array PER_MINUTE = [
        'read' => 60,
        'interact' => 30,
        'edit' => 20,
        'create' => 10,
        'heavy' => 5,
        'mail' => 3,
    ];

    /**
     * Допустимое число запросов в минуту.
     */
    public function perMinute(): int
    {
        return self::PER_MINUTE[$this->value];
    }

    /**
     * Middleware для маршрута: throttle с этим ограничителем.
     */
    public function middleware(): string
    {
        return 'throttle:' . $this->value;
    }
}
