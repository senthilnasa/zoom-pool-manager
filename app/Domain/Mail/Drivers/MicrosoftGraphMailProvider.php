<?php

namespace App\Domain\Mail\Drivers;

use App\Domain\Mail\Contracts\MailProviderInterface;
use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MicrosoftGraphMailProvider implements MailProviderInterface
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
        $senderUser = Setting::get('mail.graph_sender_user', 'me');

        $messagePayload = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $htmlBody,
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $toEmail,
                        'name' => $toName ?: $toEmail,
                    ],
                ],
            ],
        ];

        if (! empty($attachments)) {
            $formattedAttachments = [];
            foreach ($attachments as $att) {
                $formattedAttachments[] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $att['name'],
                    'contentType' => $att['mime'],
                    'contentBytes' => base64_encode($att['data']),
                ];
            }
            $messagePayload['attachments'] = $formattedAttachments;
        }

        $endpoint = "https://graph.microsoft.com/v1.0/users/{$senderUser}/sendMail";
        if ($senderUser === 'me') {
            $endpoint = 'https://graph.microsoft.com/v1.0/me/sendMail';
        }

        $response = Http::withToken($accessToken)
            ->post($endpoint, [
                'message' => $messagePayload,
                'saveToSentItems' => true,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Microsoft Graph API error: '.$response->body());
        }

        return true;
    }

    public function testConnection(): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $senderUser = Setting::get('mail.graph_sender_user', 'me');

            $endpoint = $senderUser === 'me'
                ? 'https://graph.microsoft.com/v1.0/me'
                : "https://graph.microsoft.com/v1.0/users/{$senderUser}";

            $response = Http::withToken($accessToken)->get($endpoint);

            if ($response->successful()) {
                $mail = $response->json('mail') ?? $response->json('userPrincipalName');

                return [
                    'success' => true,
                    'message' => "Successfully connected to Microsoft Graph API as {$mail}.",
                ];
            }

            return [
                'success' => false,
                'message' => 'Microsoft Graph authorization failed: '.$response->body(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Microsoft Graph error: '.$e->getMessage(),
            ];
        }
    }

    public function getDriverName(): string
    {
        return 'microsoft_graph';
    }

    protected function getAccessToken(): string
    {
        $token = Setting::get('mail.graph_access_token');
        if (! $token) {
            throw new RuntimeException('Microsoft Graph API access token is not configured.');
        }

        return $token;
    }
}
