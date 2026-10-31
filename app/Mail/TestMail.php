<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Проверочное письмо из настроек почты.
 */
final class TestMail extends Mailable
{
    /**
     * @param string $siteName название сайта для темы и текста
     */
    public function __construct(
        public readonly string $siteName,
    ) {
    }

    /**
     * Тема письма.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.test.subject', ['site' => $this->siteName]));
    }

    /**
     * Текст письма из шаблона mail.test; публичные свойства письма передаются в шаблон.
     */
    public function content(): Content
    {
        return new Content(text: 'mail.test');
    }
}
