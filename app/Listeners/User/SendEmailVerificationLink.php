<?php

declare(strict_types=1);

namespace App\Listeners\User;

use App\Contracts\UserRepository;
use App\Events\User\EmailChanged;
use App\Events\User\UserRegistered;
use App\Services\User\EmailVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Отправляет ссылку подтверждения на новый адрес после регистрации или смены email.
 */
final class SendEmailVerificationLink implements ShouldQueue
{
    /**
     * @param UserRepository           $users        пользователи
     * @param EmailVerificationService $verification подтверждение email
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly EmailVerificationService $verification,
    ) {
    }

    /**
     * Письмо уходит, если пользователь ещё существует и адрес не подтверждён.
     */
    public function handle(UserRegistered|EmailChanged $event): void
    {
        $user = $this->users->find($event->userId);

        if ($user !== null) {
            $this->verification->send($user, $event->locale);
        }
    }
}
