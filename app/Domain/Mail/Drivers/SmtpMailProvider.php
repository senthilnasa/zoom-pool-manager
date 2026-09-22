<?php

namespace App\Domain\Mail\Drivers;

use App\Domain\Mail\Contracts\MailProviderInterface;
use App\Domain\Settings\Models\Setting;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SmtpMailProvider implements MailProviderInterface
{
    public function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = []
    ): bool {
        $this->configureMailer();

        $fromAddress = Setting::get('mail.from_address', config('mail.from.address', 'noreply@zoompoolmanager.org'));
        $fromName = Setting::get('mail.from_name', config('mail.from.name', 'Zoom Pool Manager'));
        $replyTo = Setting::get('mail.reply_to', null);

        Mail::send([], [], function (Message $message) use (
            $toEmail,
            $toName,
            $fromAddress,
            $fromName,
            $replyTo,
            $subject,
            $htmlBody,
            $textBody,
            $attachments
        ) {
            $message->to($toEmail, $toName)
                ->from($fromAddress, $fromName)
                ->subject($subject)
                ->html($htmlBody)
                ->text($textBody);

            if ($replyTo) {
                $message->replyTo($replyTo);
            }

            foreach ($attachments as $attachment) {
                $message->attachData(
                    $attachment['data'],
                    $attachment['name'],
                    ['mime' => $attachment['mime']]
                );
            }
        });

        return true;
    }

    public function testConnection(): array
    {
        $host = Setting::get('mail.smtp_host', config('mail.mailers.smtp.host', '127.0.0.1'));
        $port = (int) Setting::get('mail.smtp_port', config('mail.mailers.smtp.port', 25));

        try {
            $connection = @fsockopen((string) $host, $port, $errno, $errstr, 5);
            if (! is_resource($connection)) {
                return [
                    'success' => false,
                    'message' => "SMTP connection failed to {$host}:{$port} ({$errstr})",
                ];
            }

            fclose($connection);

            return [
                'success' => true,
                'message' => "Successfully connected to SMTP server at {$host}:{$port}.",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "SMTP connection failed: {$e->getMessage()}",
            ];
        }
    }

    public function getDriverName(): string
    {
        return 'smtp';
    }

    protected function configureMailer(): void
    {
        $host = Setting::get('mail.smtp_host');
        if ($host) {
            Config::set('mail.mailers.smtp.host', $host);
            Config::set('mail.mailers.smtp.port', Setting::get('mail.smtp_port', 587));
            Config::set('mail.mailers.smtp.encryption', Setting::get('mail.smtp_encryption', 'tls'));
            Config::set('mail.mailers.smtp.username', Setting::get('mail.smtp_username'));
            Config::set('mail.mailers.smtp.password', Setting::get('mail.smtp_password'));
        }
    }
}
