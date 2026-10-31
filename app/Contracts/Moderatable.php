<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Материал, который проверяет модератор: статья или альбом.
 */
interface Moderatable
{
    /**
     * Публикует материал.
     */
    public function publish(): void;

    /**
     * Отклоняет материал с причиной для автора.
     */
    public function reject(string $reason): void;

    /**
     * Возвращает материал на проверку.
     */
    public function sendToModeration(): void;
}
