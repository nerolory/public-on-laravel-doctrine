<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Письмо со ссылкой подтверждения email. Уходит через очередь, поэтому хранит только строки:
 * сущности Doctrine в задачи очереди не сериализуются.
 */
final class VerifyEmailMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param string $url      подписанная ссылка подтверждения
     * @param string $siteName название сайта для темы и текста
     * @param string $locale   язык письма
     */
    public function __construct(
        public readonly string $url,
        public readonly string $siteName,
        string $locale,
    ) {
        $this->locale($locale);
    }

    /**
     * Тема письма.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.verify.subject', ['site' => $this->siteName]));
    }

    /**
     * Текст письма из шаблона mail.verify; публичные свойства письма передаются в шаблон.
     */
    public function content(): Content
    {
        return new Content(text: 'mail.verify');
    }
}
