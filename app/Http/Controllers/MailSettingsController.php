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

    public function index(): View|JsonResponse
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

        if (request()->wantsJson()) {
            return response()->json([
                'provider' => $currentProvider,
                'current_provider' => $currentProvider,
                'from_address' => $fromAddress,
                'from_name' => $fromName,
                'reply_to' => $replyTo,
                'smtp_host' => $smtpHost,
                'smtp_port' => $smtpPort,
                'smtp_encryption' => $smtpEncryption,
                'smtp_username' => $smtpUsername,
            ]);
        }

        return app(SpaController::class)->index(request(), [
            'fallbackHtml' => '<h1>Mail Settings</h1>',
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        if (! $request->has('provider') && $request->has('current_provider')) {
            $request->merge(['provider' => $request->input('current_provider')]);
        }
        if (! $request->has('current_provider') && $request->has('provider')) {
            $request->merge(['current_provider' => $request->input('provider')]);
        }

        $validated = $request->validate([
            'provider' => 'required|in:smtp,gmail,graph,log,sendgrid,ses,postmark',
            'current_provider' => 'nullable|string',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'reply_to' => 'nullable|email|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|numeric',
            'smtp_encryption' => 'nullable|in:tls,ssl,none,null',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string',
            'gmail_access_token' => 'nullable|string',
            'graph_access_token' => 'nullable|string',
            'graph_sender_user' => 'nullable|string|max:255',
        ]);

        $encryption = $validated['smtp_encryption'] ?? 'tls';
        if ($encryption === 'null') {
            $encryption = 'none';
        }

        Setting::set('mail.provider', $validated['provider']);
        Setting::set('mail.from_address', $validated['from_address']);
        Setting::set('mail.from_name', $validated['from_name']);
        Setting::set('mail.reply_to', $validated['reply_to'] ?? '');

        if (in_array($validated['provider'], ['smtp', 'sendgrid', 'ses', 'postmark'], true)) {
            Setting::set('mail.smtp_host', $validated['smtp_host'] ?? '');
            Setting::set('mail.smtp_port', $validated['smtp_port'] ?? 587);
            Setting::set('mail.smtp_encryption', $encryption);
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

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Mail settings updated successfully.']);
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
        $recipient = $request->input('test_email', $request->input('recipient', $request->input('to_email')));

        if (empty($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'A valid recipient email address is required.',
            ], 422);
        }

        try {
            $provider = $this->providerManager->getProvider();
            $success = $provider->send(
                toEmail: (string) $recipient,
                toName: 'Test Recipient',
                subject: 'Test Email from Zoom Pool Manager',
                htmlBody: '<p>This is a test email sent from <strong>Zoom Pool Manager</strong>. Your mail provider is configured correctly.</p>',
                textBody: 'This is a test email sent from Zoom Pool Manager. Your mail provider is configured correctly.'
            );

            return response()->json([
                'success' => $success,
                'message' => "Test email successfully sent to {$recipient}.",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: '.$e->getMessage(),
            ], 422);
        }
    }

    public function deliveries(Request $request): View|JsonResponse
    {
        $status = $request->input('status');

        $query = EmailDelivery::with('meeting')->latest();

        if ($status && in_array($status, ['queued', 'sending', 'sent', 'failed'], true)) {
            $query->where('status', $status);
        }

        $deliveries = $query->paginate(25);

        if ($request->wantsJson()) {
            return response()->json($deliveries);
        }

        return app(SpaController::class)->index(request(), [
            'fallbackHtml' => '<h1>Mail Deliveries</h1>',
        ]);
    }

    public function retryDelivery(string $id): RedirectResponse|JsonResponse
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

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Email delivery requeued.']);
        }

        return back()->with('success', 'Email delivery requeued.');
    }
}
