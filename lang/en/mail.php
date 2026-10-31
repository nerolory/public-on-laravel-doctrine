<?php

return [
    'verify' => [
        'subject' => 'Confirm your email on ":site"',
        'body' => 'To confirm your email address on ":site", open this link:',
        'expires' => 'The link is valid for 24 hours. If you did not sign up, just delete this email.',
        'sent' => 'A confirmation email has been sent.',
    ],
    'test' => [
        'subject' => 'Mail check: :site',
        'body' => 'This is a test email from ":site". If you received it, mail is configured correctly.',
        'button' => 'Send a test email',
        'hint' => 'The email goes to your address :email using the saved settings.',
        'sent' => 'A test email was sent to :email.',
        'failed' => 'Could not send the email: :error',
        'logged' => 'SMTP sending is off, so the email was written to the application log.',
    ],
];
