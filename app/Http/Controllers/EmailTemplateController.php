<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Models\EmailTemplate;
use App\Domain\Communication\Services\EmailTemplateRegistry;
use App\Domain\Communication\Services\TemplateRenderer;
use App\Domain\Mail\Services\MailProviderManager;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class EmailTemplateController extends Controller
{
    public function __construct(
        protected TemplateRenderer $renderer,
        protected MailProviderManager $mailProviderManager
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        // Self-heal/seed missing templates into the database
        EmailTemplateRegistry::seedMissingTemplates();

        if ($request->wantsJson()) {
            $templates = EmailTemplate::orderBy('category')->orderBy('name')->get();

            return response()->json($templates);
        }

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Email Templates</h1>',
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $template = EmailTemplate::where('public_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $default = EmailTemplateRegistry::getDefaultTemplate($template->key);

        return response()->json([
            'template' => $template,
            'default' => $default,
        ]);
    }

    public function edit(string $id): View
    {
        return app(SpaController::class)->index(request(), [
            'fallbackHtml' => '<h1>Edit Email Template</h1>',
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $template = EmailTemplate::where('public_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'subject_template' => 'required|string|max:255',
            'body_html_template' => 'required|string',
            'body_text_template' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? $template->category,
            'description' => $validated['description'] ?? $template->description,
            'subject_template' => $validated['subject_template'],
            'body_html_template' => $validated['body_html_template'],
            'body_text_template' => $validated['body_text_template'],
            'is_active' => ! empty($validated['is_active']),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Template \"{$template->name}\" saved successfully.",
                'template' => $template->fresh(),
            ]);
        }

        return redirect()->route('admin.templates.index')->with('success', 'Email template updated successfully.');
    }

    public function preview(Request $request, string $id): JsonResponse
    {
        $template = EmailTemplate::where('public_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $subject = (string) $request->input('subject_template', $template->subject_template);
        $html = (string) $request->input('body_html_template', $template->body_html_template);
        $text = (string) $request->input('body_text_template', $template->body_text_template);

        // Rich contextual mock data based on template key
        $defaultContext = EmailTemplateRegistry::getMockContext($template->key);
        $customContext = $request->input('custom_context', []);

        $context = array_merge($defaultContext, is_array($customContext) ? $customContext : []);

        $rendered = $this->renderer->render($subject, $html, $text, $context);

        return response()->json([
            'subject' => $rendered['subject'],
            'html' => $rendered['html'],
            'text' => $rendered['text'],
            'context' => $context,
            'available_variables' => $template->available_variables ?: (EmailTemplateRegistry::getDefaultTemplate($template->key)['available_variables'] ?? []),
        ]);
    }

    public function reset(string $id): JsonResponse
    {
        $template = EmailTemplate::where('public_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $reset = EmailTemplateRegistry::resetToDefault($template);

        return response()->json([
            'success' => true,
            'message' => "Template \"{$reset->name}\" reset to factory default.",
            'template' => $reset,
        ]);
    }

    public function resetAll(): JsonResponse
    {
        $count = EmailTemplateRegistry::resetAllToDefaults();

        return response()->json([
            'success' => true,
            'message' => "Successfully reset {$count} email templates to factory defaults.",
        ]);
    }

    public function sendTest(Request $request, string $id): JsonResponse
    {
        $template = EmailTemplate::where('public_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        /** @var User|null $user */
        $user = $request->user();

        $recipientEmail = trim((string) $request->input('recipient_email', $user->email ?? ''));

        if (empty($recipientEmail) || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'A valid recipient email address is required.',
            ], 422);
        }

        $subject = (string) $request->input('subject_template', $template->subject_template);
        $html = (string) $request->input('body_html_template', $template->body_html_template);
        $text = (string) $request->input('body_text_template', $template->body_text_template);

        $mockContext = EmailTemplateRegistry::getMockContext($template->key);
        $mockContext['recipient.email'] = $recipientEmail;
        $mockContext['recipient_email'] = $recipientEmail;
        if ($user) {
            $mockContext['recipient.name'] = $user->name;
            $mockContext['recipient_name'] = $user->name;
        }

        $rendered = $this->renderer->render($subject, $html, $text, $mockContext);

        try {
            $provider = $this->mailProviderManager->getProvider();
            $success = $provider->send(
                toEmail: $recipientEmail,
                toName: (string) ($mockContext['recipient.name'] ?? 'Test Recipient'),
                subject: '[TEST] '.$rendered['subject'],
                htmlBody: $rendered['html'],
                textBody: $rendered['text']
            );

            return response()->json([
                'success' => $success,
                'message' => "Test email for \"{$template->name}\" sent successfully to {$recipientEmail}.",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: '.$e->getMessage(),
            ], 422);
        }
    }
}
