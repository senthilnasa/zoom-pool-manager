<?php

namespace App\Domain\Mail\Drivers;

use App\Domain\Mail\Contracts\MailProviderInterface;
use Illuminate\Support\Facades\Log;

class LogMailProvider implements MailProviderInterface
{
    /**
     * @var array<int, array{toEmail: string, toName: ?string, subject: string, htmlBody: string, textBody: string, attachments: array<int, array{name: string, data: string, mime: string}>}>
     */
    public static array $sentMessages = [];

    public function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = []
    ): bool {
        self::$sentMessages[] = [
            'toEmail' => $toEmail,
            'toName' => $toName,
            'subject' => $subject,
            'htmlBody' => $htmlBody,
            'textBody' => $textBody,
            'attachments' => $attachments,
        ];

        Log::channel('single')->info("Email sent to [{$toEmail}]: {$subject}");

        return true;
    }

    public function testConnection(): array
    {
        return [
            'success' => true,
            'message' => 'Log driver is active and ready to log messages.',
        ];
    }

    public function getDriverName(): string
    {
        return 'log';
    }

    public static function reset(): void
    {
        self::$sentMessages = [];
    }
}
