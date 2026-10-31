<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\User;
use App\Mail\VerifyEmailMail;
use Illuminate\Support\Facades\Mail;
use Tests\DoctrineTestCase;

/**
 * Подтверждение почты: ссылка из письма, сброс подтверждения при смене адреса и устаревшие ссылки.
 */
final class EmailVerificationTest extends DoctrineTestCase
{
    /**
     * Ссылка из письма подтверждает почту; подделанная подпись отклоняется.
     */
    public function test_registration_sends_signed_link_that_verifies_email(): void
    {
        Mail::fake();
        $this->registerUser(['name' => 'Иван', 'email' => 'ivan@example.test'])->assertCreated();

        $url = $this->verificationUrl('ivan@example.test');
        $this->getJson('/api/session')->assertJsonPath('user.email_verified', false);

        $this->get(str_replace('signature=', 'signature=0', $url))->assertForbidden();
        $this->get($url)->assertRedirect('/account?verified=1');
        $this->getJson('/api/session')->assertJsonPath('user.email_verified', true);
    }

    /**
     * Смена адреса сбрасывает подтверждение и отправляет новую ссылку; повторная отправка ставит письмо в очередь.
     */
    public function test_changing_email_resets_verification_and_sends_new_link(): void
    {
        Mail::fake();
        $user = $this->createUser('old@example.test', 'Иван');
        $user->markEmailVerified();
        $this->em->flush();
        $this->actingAs($user);

        $this->putJson('/api/user', ['name' => 'Иван', 'email' => 'new@example.test'])->assertOk();
        Mail::assertQueued(
            VerifyEmailMail::class,
            fn (VerifyEmailMail $mail): bool => $mail->hasTo('new@example.test'),
        );
        $this->em->clear();
        self::assertNull($this->em->find(User::class, $user->getAuthIdentifier())->getEmailVerifiedAt());

        $this->postJson('/api/email/resend')->assertOk();
        Mail::assertQueued(VerifyEmailMail::class, 2);
    }

    /**
     * Ссылка, отправленная на прежний адрес, после смены почты не подтверждает аккаунт.
     */
    public function test_link_for_previous_email_is_rejected(): void
    {
        Mail::fake();
        $this->registerUser(['name' => 'Иван', 'email' => 'first@example.test'])->assertCreated();
        $url = $this->verificationUrl('first@example.test');
        $this->putJson('/api/user', ['name' => 'Иван', 'email' => 'second@example.test'])->assertOk();

        $this->get($url)->assertRedirect('/account?verified=0');
    }

    /**
     * Ссылка подтверждения из письма, поставленного в очередь на адрес.
     */
    private function verificationUrl(string $email): string
    {
        $url = null;
        Mail::assertQueued(VerifyEmailMail::class, static function (VerifyEmailMail $mail) use ($email, &$url): bool {
            $url = $mail->url;

            return $mail->hasTo($email);
        });

        return $url;
    }
}
