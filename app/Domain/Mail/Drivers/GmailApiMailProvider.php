<?php

namespace App\Domain\Mail\Drivers;

use App\Domain\Mail\Contracts\MailProviderInterface;
use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GmailApiMailProvider implements MailProviderInterface
{
    public function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = []
    ): bool {
        $accessToken = $this->getAccessToken();
        $fromAddress = Setting::get('mail.from_address', 'noreply@zoompoolmanager.org');
        $fromName = Setting::get('mail.from_name', 'Zoom Pool Manager');

        $mimeMessage = $this->buildRawMimeMessage(
            toEmail: $toEmail,
            toName: $toName,
            fromEmail: $fromAddress,
            fromName: $fromName,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            attachments: $attachments
        );

        $base64UrlMessage = rtrim(strtr(base64_encode($mimeMessage), '+/', '-_'), '=');

        $response = Http::withToken($accessToken)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $base64UrlMessage,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gmail API error: '.$response->body());
        }

        return true;
    }

    public function testConnection(): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $response = Http::withToken($accessToken)
                ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile');

            if ($response->successful()) {
                $email = $response->json('emailAddress');

                return [
                    'success' => true,
                    'message' => "Successfully connected to Gmail API as {$email}.",
                ];
            }

            return [
                'success' => false,
                'message' => 'Gmail API authorization failed: '.$response->body(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gmail API error: '.$e->getMessage(),
            ];
        }
    }

    public function getDriverName(): string
    {
        return 'gmail';
    }

    protected function getAccessToken(): string
    {
        $token = Setting::get('mail.gmail_access_token');
        if (! $token) {
            throw new RuntimeException('Gmail API access token is not configured.');
        }

        return $token;
    }

    /**
     * @param  array<int, array{name: string, data: string, mime: string}>  $attachments
     */
    protected function buildRawMimeMessage(
        string $toEmail,
        ?string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = []
    ): string {
        $boundary = 'boundary_'.md5(uniqid((string) time(), true));
        $toHeader = $toName ? "{$toName} <{$toEmail}>" : $toEmail;
        $fromHeader = "{$fromName} <{$fromEmail}>";

        $headers = [
            "From: {$fromHeader}",
            "To: {$toHeader}",
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
        ];

        $body = implode("\r\n", $headers)."\r\n\r\n";

        // HTML & Plain text part
        $altBoundary = 'alt_boundary_'.md5(uniqid((string) time(), true));
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";

        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody))."\r\n";

        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody))."\r\n";

        $body .= "--{$altBoundary}--\r\n";

        // Attachments
        foreach ($attachments as $attachment) {
            $filename = $attachment['name'];
            $mime = $attachment['mime'];
            $data = $attachment['data'];

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: {$mime}; name=\"{$filename}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($data))."\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        return $body;
    }
}
