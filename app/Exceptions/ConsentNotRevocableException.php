<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Согласие на обработку данных и с правилами сайта отзывается только удалением учётной записи: ответ 409.
 */
final class ConsentNotRevocableException extends RuntimeException
{
    /**
     * Сообщение берётся из переводов legal.not_revocable.
     */
    public function __construct()
    {
        parent::__construct(__('legal.not_revocable'));
    }

    /**
     * Ожидаемая ошибка пользователя: в журнал не пишется.
     */
    public function report(): void
    {
    }

    /**
     * Ответ API с сообщением и кодом 409.
     */
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], Response::HTTP_CONFLICT);
    }
}
