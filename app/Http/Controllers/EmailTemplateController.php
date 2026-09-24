<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Models\EmailTemplate;
use App\Domain\Communication\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function __construct(
        protected TemplateRenderer $renderer
    ) {}

    public function index(): View|JsonResponse
    {
        if (request()->wantsJson()) {
            return response()->json(EmailTemplate::orderBy('name')->get());
        }

        return app(SpaController::class)->index(request(), [
            'fallbackHtml' => '<h1>Email Templates</h1>',
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
            'subject_template' => 'required|string|max:255',
            'body_html_template' => 'required|string',
            'body_text_template' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update([
            'name' => $validated['name'],
            'subject_template' => $validated['subject_template'],
            'body_html_template' => $validated['body_html_template'],
            'body_text_template' => $validated['body_text_template'],
            'is_active' => ! empty($validated['is_active']),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'template' => $template->fresh()]);
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

        // Dummy preview context
        $context = [
            'recipient_name' => 'Dr. Jane Sample',
            'recipient_email' => 'jane.sample@university.edu',
            'meeting.title' => 'Advanced Robotics Lecture',
            'meeting.starts_at' => now()->addDay()->format('Y-m-d H:i T'),
            'meeting.ends_at' => now()->addDay()->addHour()->format('Y-m-d H:i T'),
            'meeting.duration_minutes' => '60',
            'meeting.join_url' => 'https://zoom.us/j/1234567890',
            'meeting.passcode' => '987654',
            'review_url' => url('/approvals'),
            'reason' => 'Resource requested during peak exam window.',
        ];

        $rendered = $this->renderer->render($subject, $html, $text, $context);

        return response()->json($rendered);
    }
}
