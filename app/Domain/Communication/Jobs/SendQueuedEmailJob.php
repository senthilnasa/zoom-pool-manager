<?php

namespace App\Domain\Communication\Jobs;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Models\EmailDelivery;
use App\Domain\Mail\Services\MailProviderManager;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendQueuedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * @param  array<int, array{name: string, data: string, mime: string}>  $attachments
     */
    public function __construct(
        public EmailDelivery $delivery,
        public array $attachments = []
    ) {}

    public function handle(MailProviderManager $providerManager, AuditService $auditService): void
    {
        // If already delivered, abort
        if ($this->delivery->status === 'sent') {
            return;
        }

        $this->delivery->update([
            'status' => 'sending',
            'attempts' => $this->delivery->attempts + 1,
        ]);

        try {
            $provider = $providerManager->getProvider();

            $provider->send(
                toEmail: $this->delivery->recipient_email,
                toName: $this->delivery->recipient_name,
                subject: $this->delivery->subject,
                htmlBody: $this->delivery->body_html ?? '',
                textBody: $this->delivery->body_text ?? '',
                attachments: $this->attachments
            );

            $this->delivery->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
                'error_message' => null,
            ]);

            $auditService->log(
                event: 'email.sent',
                auditable: $this->delivery,
                newValues: [
                    'recipient' => $this->delivery->recipient_email,
                    'subject' => $this->delivery->subject,
                    'template' => $this->delivery->template_key,
                ]
            );
        } catch (Throwable $e) {
            $this->delivery->update([
                'status' => $this->attempts() >= $this->tries ? 'failed' : 'queued',
                'error_message' => $e->getMessage(),
            ]);

            $auditService->log(
                event: 'email.failed',
                auditable: $this->delivery,
                newValues: [
                    'recipient' => $this->delivery->recipient_email,
                    'subject' => $this->delivery->subject,
                    'error' => $e->getMessage(),
                    'attempt' => $this->delivery->attempts,
                ]
            );

            throw $e;
        }
    }
}
