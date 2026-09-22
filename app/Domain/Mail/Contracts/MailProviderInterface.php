<?php

namespace App\Domain\Mail\Contracts;

interface MailProviderInterface
{
    /**
     * Send an email message.
     *
     * @param  string  $toEmail  Recipient email address
     * @param  string|null  $toName  Recipient full name
     * @param  string  $subject  Subject line
     * @param  string  $htmlBody  Rendered HTML content
     * @param  string  $textBody  Rendered plain text content
     * @param  array<int, array{name: string, data: string, mime: string}>  $attachments  Optional in-memory attachments
     * @return bool True if successfully dispatched/sent
     */
    public function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = []
    ): bool;

    /**
     * Test provider connectivity and credentials.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Get provider name.
     */
    public function getDriverName(): string;
}
