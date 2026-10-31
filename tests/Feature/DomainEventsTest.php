<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Article;
use App\Events\Complaint\ComplaintFiled;
use App\Events\User\AccountDeleted;
use App\Events\User\EmailChanged;
use App\Events\User\UserRegistered;
use App\Listeners\Complaint\HideOnComplaintThreshold;
use App\Listeners\Legal\RevokeConsentsOfDeletedAccount;
use App\Listeners\Mail\ApplyMailSettings;
use App\Listeners\User\SendEmailVerificationLink;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\DoctrineTestCase;

/**
 * События домена: привязка слушателей, отправка через очередь и условия, при которых событие возникает.
 */
final class DomainEventsTest extends DoctrineTestCase
{
    /**
     * Каждое событие связано со своим слушателем.
     */
    public function test_listeners_are_bound_to_events(): void
    {
        Event::fake();

        Event::assertListening(UserRegistered::class, SendEmailVerificationLink::class);
        Event::assertListening(EmailChanged::class, SendEmailVerificationLink::class);
        Event::assertListening(AccountDeleted::class, RevokeConsentsOfDeletedAccount::class);
        Event::assertListening(ComplaintFiled::class, HideOnComplaintThreshold::class);
        Event::assertListening(JobProcessing::class, ApplyMailSettings::class);
    }

    /**
     * Ссылка подтверждения почты уходит в очередь после регистрации.
     */
    public function test_verification_link_is_sent_through_queue(): void
    {
        Queue::fake();

        $this->registerUser(['name' => 'Иван', 'email' => 'ivan@example.test'])->assertCreated();

        Queue::assertPushed(
            CallQueuedListener::class,
            static fn (CallQueuedListener $job): bool => $job->class === SendEmailVerificationLink::class
                && $job->data[0] instanceof UserRegistered,
        );
    }

    /**
     * Событие смены почты возникает только при указании нового адреса.
     */
    public function test_email_change_event_fires_only_for_new_address(): void
    {
        Event::fake([EmailChanged::class]);
        $user = $this->createUser('same@example.test', 'Иван');
        $this->actingAs($user);

        $this->putJson('/api/user', ['name' => 'Иван', 'email' => 'same@example.test'])->assertOk();
        Event::assertNotDispatched(EmailChanged::class);

        $this->putJson('/api/user', ['name' => 'Иван', 'email' => 'other@example.test'])->assertOk();
        Event::assertDispatched(
            EmailChanged::class,
            static fn (EmailChanged $event): bool => $event->userId === $user->getAuthIdentifier(),
        );
    }

    /**
     * Удаление аккаунта передаёт в событии согласия, которые пользователь отозвал.
     */
    public function test_account_deletion_event_carries_revoked_consents(): void
    {
        $this->seedLegalDocuments();
        Event::fake([AccountDeleted::class]);
        $this->registerUser([
            'name' => 'Новый', 'email' => 'new@example.test',
            'consents' => ['personal_data' => true, 'site_rules' => true, 'age' => true],
        ])->assertCreated();

        $this->deleteJson('/api/user', ['password' => self::PASSWORD, 'revoke' => ['personal_data']])->assertOk();

        Event::assertDispatched(
            AccountDeleted::class,
            static fn (AccountDeleted $event): bool => $event->user->getEmail() === 'new@example.test'
                && $event->revokedConsents->map(static fn ($type): string => $type->value)->all() === ['personal_data'],
        );
    }

    /**
     * Новая жалоба порождает событие с объектом жалобы.
     */
    public function test_new_complaint_dispatches_event(): void
    {
        Event::fake([ComplaintFiled::class]);
        $article = $this->createArticle($this->createUser('author@example.test'));
        $this->actingAs($this->createUser('reader@example.test'));

        $this->postJson('/api/complaints', [
            'target' => 'article', 'id' => (string) $article->getId(), 'reason' => 'hate',
        ])
            ->assertCreated();

        Event::assertDispatched(
            ComplaintFiled::class,
            static fn (ComplaintFiled $event): bool => $event->target === 'article'
                && $event->entity instanceof Article
                && $event->entity->getId() === $article->getId(),
        );
    }
}
