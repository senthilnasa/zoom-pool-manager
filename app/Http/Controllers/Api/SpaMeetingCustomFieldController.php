<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\MeetingCustomField;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SpaMeetingCustomFieldController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all meeting custom fields.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MeetingCustomField::orderBy('display_order', 'asc')->orderBy('id', 'asc');

        // Non-admins only see active fields
        $user = $request->user();
        if (! $user || (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator') && ! $user->hasRole('super_admin') && ! $user->can('settings.manage'))) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'fields' => $query->get(),
        ]);
    }

    /**
     * Create a new custom field.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('Super Administrator') || $user->hasRole('Administrator') || $user->hasRole('super_admin') || $user->can('settings.manage')), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'field_key' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:meeting_custom_fields,field_key'],
            'field_type' => ['required', 'string', 'in:text,textarea,dropdown,int'],
            'options' => ['nullable'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $fieldKey = ! empty($validated['field_key'])
            ? Str::slug($validated['field_key'], '_')
            : Str::slug($validated['name'], '_');

        // Ensure uniqueness if slug was generated
        $origKey = $fieldKey;
        $counter = 1;
        while (MeetingCustomField::where('field_key', $fieldKey)->exists()) {
            $fieldKey = "{$origKey}_{$counter}";
            $counter++;
        }

        // Format options for dropdown
        $options = null;
        if ($validated['field_type'] === 'dropdown') {
            if (is_array($validated['options'] ?? null)) {
                $options = array_values(array_filter(array_map('trim', $validated['options'])));
            } elseif (is_string($validated['options'] ?? null)) {
                $options = array_values(array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $validated['options'])))));
            }
        }

        $field = MeetingCustomField::create([
            'name' => $validated['name'],
            'field_key' => $fieldKey,
            'field_type' => $validated['field_type'],
            'options' => $options,
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
            'default_value' => $validated['default_value'] ?? null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'display_order' => (int) ($validated['display_order'] ?? 0),
        ]);

        $this->auditService->log('meeting_custom_field.created', $field, null, $field->toArray(), $user);

        return response()->json([
            'success' => true,
            'message' => 'Custom field created successfully.',
            'field' => $field,
        ], 201);
    }

    /**
     * Update an existing custom field.
     */
    public function update(Request $request, string $publicId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('Super Administrator') || $user->hasRole('Administrator') || $user->hasRole('super_admin') || $user->can('settings.manage')), 403);

        /** @var MeetingCustomField $field */
        $field = MeetingCustomField::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'field_key' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:meeting_custom_fields,field_key,'.$field->id],
            'field_type' => ['required', 'string', 'in:text,textarea,dropdown,int'],
            'options' => ['nullable'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $options = null;
        if ($validated['field_type'] === 'dropdown') {
            if (is_array($validated['options'] ?? null)) {
                $options = array_values(array_filter(array_map('trim', $validated['options'])));
            } elseif (is_string($validated['options'] ?? null)) {
                $options = array_values(array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $validated['options'])))));
            }
        }

        $oldValues = $field->toArray();

        $field->update([
            'name' => $validated['name'],
            'field_key' => ! empty($validated['field_key']) ? Str::slug($validated['field_key'], '_') : $field->field_key,
            'field_type' => $validated['field_type'],
            'options' => $options,
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
            'default_value' => $validated['default_value'] ?? null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'display_order' => (int) ($validated['display_order'] ?? 0),
        ]);

        $this->auditService->log('meeting_custom_field.updated', $field, null, [
            'old' => $oldValues,
            'new' => $field->toArray(),
        ], $user);

        return response()->json([
            'success' => true,
            'message' => 'Custom field updated successfully.',
            'field' => $field,
        ]);
    }

    /**
     * Delete a custom field.
     */
    public function destroy(Request $request, string $publicId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('Super Administrator') || $user->hasRole('Administrator') || $user->hasRole('super_admin') || $user->can('settings.manage')), 403);

        /** @var MeetingCustomField $field */
        $field = MeetingCustomField::where('public_id', $publicId)->firstOrFail();
        $oldValues = $field->toArray();
        $field->delete();

        $this->auditService->log('meeting_custom_field.deleted', null, null, $oldValues, $user);

        return response()->json([
            'success' => true,
            'message' => 'Custom field deleted successfully.',
        ]);
    }
}
