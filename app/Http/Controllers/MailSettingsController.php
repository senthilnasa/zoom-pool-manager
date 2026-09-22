<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Jobs\SendQueuedEmailJob;
use App\Domain\Communication\Models\EmailDelivery;
use App\Domain\Mail\Services\MailProviderManager;
use App\Domain\Settings\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class MailSettingsController extends Controller
{
    public function __construct(
        protected MailProviderManager $providerManager
    ) {}

    public function index(): View
    {
        $currentProvider = Setting::get('mail.provider', config('mail.default', 'smtp'));
        $fromAddress = Setting::get('mail.from_address', config('mail.from.address', 'noreply@zoompoolmanager.org'));
        $fromName = Setting::get('mail.from_name', config('mail.from.name', 'Zoom Pool Manager'));
        $replyTo = Setting::get('mail.reply_to', '');

        // SMTP settings
        $smtpHost = Setting::get('mail.smtp_host', config('mail.mailers.smtp.host', '127.0.0.1'));
        $smtpPort = Setting::get('mail.smtp_port', config('mail.mailers.smtp.port', 587));
        $smtpEncryption = Setting::get('mail.smtp_encryption', config('mail.mailers.smtp.encryption', 'tls'));
        $smtpUsername = Setting::get('mail.smtp_username', config('mail.mailers.smtp.username', ''));

        return view('mail.settings', compact(
            'currentProvider',
            'fromAddress',
            'fromName',
            'replyTo',
            'smtpHost',
            'smtpPort',
            'smtpEncryption',
            'smtpUsername'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:smtp,gmail,graph,log',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'reply_to' => 'nullable|email|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|numeric',
            'smtp_encryption' => 'nullable|in:tls,ssl,none',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string',
            'gmail_access_token' => 'nullable|string',
            'graph_access_token' => 'nullable|string',
            'graph_sender_user' => 'nullable|string|max:255',
        ]);

        Setting::set('mail.provider', $validated['provider']);
        Setting::set('mail.from_address', $validated['from_address']);
        Setting::set('mail.from_name', $validated['from_name']);
        Setting::set('mail.reply_to', $validated['reply_to'] ?? '');

        if ($validated['provider'] === 'smtp') {
            Setting::set('mail.smtp_host', $validated['smtp_host'] ?? '');
            Setting::set('mail.smtp_port', $validated['smtp_port'] ?? 587);
            Setting::set('mail.smtp_encryption', $validated['smtp_encryption'] ?? 'tls');
            Setting::set('mail.smtp_username', $validated['smtp_username'] ?? '');
            if (! empty($validated['smtp_password'])) {
                Setting::set('mail.smtp_password', $validated['smtp_password']);
            }
        } elseif ($validated['provider'] === 'gmail') {
            if (! empty($validated['gmail_access_token'])) {
                Setting::set('mail.gmail_access_token', $validated['gmail_access_token']);
            }
        } elseif ($validated['provider'] === 'graph') {
            if (! empty($validated['graph_access_token'])) {
                Setting::set('mail.graph_access_token', $validated['graph_access_token']);
            }
            if (! empty($validated['graph_sender_user'])) {
                Setting::set('mail.graph_sender_user', $validated['graph_sender_user']);
            }
        }

        return redirect()->route('admin.mail.index')->with('success', 'Mail settings updated successfully.');
    }

    public function testConnection(): JsonResponse
    {
        try {
            $provider = $this->providerManager->getProvider();
            $result = $provider->testConnection();

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test error: '.$e->getMessage(),
            ], 422);
        }
    }

    public function testSend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            $provider = $this->providerManager->getProvider();
            $success = $provider->send(
                toEmail: $validated['test_email'],
                toName: 'Test Recipient',
                subject: 'Test Email from Zoom Pool Manager',
                htmlBody: '<p>This is a test email sent from <strong>Zoom Pool Manager</strong>. Your mail provider is configured correctly.</p>',
                textBody: 'This is a test email sent from Zoom Pool Manager. Your mail provider is configured correctly.'
            );

            return response()->json([
                'success' => $success,
                'message' => "Test email successfully sent to {$validated['test_email']}.",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: '.$e->getMessage(),
            ], 422);
        }
    }

    public function deliveries(Request $request): View
    {
        $status = $request->input('status');

        $query = EmailDelivery::with('meeting')->latest();

        if ($status && in_array($status, ['queued', 'sending', 'sent', 'failed'], true)) {
            $query->where('status', $status);
        }

        $deliveries = $query->paginate(25);

        return view('mail.deliveries', compact('deliveries', 'status'));
    }

    public function retryDelivery(string $id): RedirectResponse
    {
        $delivery = EmailDelivery::where('public_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $delivery->update([
            'status' => 'queued',
            'attempts' => 0,
            'error_message' => null,
        ]);

        SendQueuedEmailJob::dispatch($delivery);

        return back()->with('success', 'Email delivery requeued.');
    }
}
