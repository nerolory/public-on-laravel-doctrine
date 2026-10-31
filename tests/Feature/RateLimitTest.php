<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Http\RateLimit;
use Closure;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\DoctrineTestCase;

/**
 * Ограничение частоты запросов: каждая категория из RateLimit отвечает 429 после лимита в минуту.
 */
final class RateLimitTest extends DoctrineTestCase
{
    /**
     * Категория чтения: подсказки тегов ограничены лимитом на IP-адрес гостя.
     */
    public function test_read_category_limits_guest_requests(): void
    {
        $this->assertThrottled(
            RateLimit::Read,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            fn (): TestResponse => $this->getJson('/api/tags'),
        );
    }

    /**
     * Категория создания: вход и регистрация гостя ограничены лимитом на IP-адрес.
     */
    public function test_create_category_limits_guest_requests(): void
    {
        $this->assertThrottled(
            RateLimit::Create,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            fn (): TestResponse => $this->postJson('/api/login', []),
        );
    }

    /**
     * Категория действий: загрузка и сохранение графика ограничены лимитом на пользователя.
     */
    public function test_interact_category_limits_user_requests(): void
    {
        $this->actingAs($this->createUser('author@example.test'));

        $this->assertThrottled(
            RateLimit::Interact,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            fn (): TestResponse => $this->postJson('/api/charts', []),
        );
    }

    /**
     * Категория правок: изменение аккаунта ограничено лимитом на пользователя.
     */
    public function test_edit_category_limits_user_requests(): void
    {
        $this->actingAs($this->createUser('author@example.test'));

        $this->assertThrottled(
            RateLimit::Edit,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            fn (): TestResponse => $this->putJson('/api/user', []),
        );
    }

    /**
     * Категория тяжёлых операций: выгрузка данных ограничена лимитом на пользователя.
     * В регионе по умолчанию выгрузки нет, поэтому запросы до лимита отвечают 404.
     */
    public function test_heavy_category_limits_user_requests(): void
    {
        $this->actingAs($this->createUser('author@example.test'));

        $this->assertThrottled(
            RateLimit::Heavy,
            Response::HTTP_NOT_FOUND,
            fn (): TestResponse => $this->getJson('/api/user/export'),
        );
    }

    /**
     * Категория писем: повторная отправка письма подтверждения ограничена лимитом на пользователя.
     */
    public function test_mail_category_limits_user_requests(): void
    {
        Mail::fake();
        $this->actingAs($this->createUser('author@example.test'));

        $this->assertThrottled(
            RateLimit::Mail,
            Response::HTTP_OK,
            fn (): TestResponse => $this->postJson('/api/email/resend'),
        );
    }

    /**
     * Запросы до лимита получают обычный ответ, следующий запрос получает 429 Too Many Requests.
     *
     * @param int                     $status  ответ на запрос в пределах лимита
     * @param Closure(): TestResponse $request запрос, который расходует лимит
     */
    private function assertThrottled(RateLimit $limit, int $status, Closure $request): void
    {
        for ($attempt = 1; $attempt <= $limit->perMinute(); $attempt++) {
            self::assertSame(
                $status,
                $request()->status(),
                "Запрос {$attempt} из {$limit->perMinute()} в категории «{$limit->value}» не ограничен.",
            );
        }

        $request()->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }
}
