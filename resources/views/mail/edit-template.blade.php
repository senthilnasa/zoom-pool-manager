@extends('layouts.base')

@section('title', 'Edit Template - ' . $template->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="templateEditor()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.templates.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Templates</a>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">Edit: {{ $template->name }}</h1>
            <p class="text-xs font-mono text-slate-400">{{ $template->key }}</p>
        </div>
    </div>

    <!-- Edit Form -->
    <form action="{{ route('admin.templates.update', $template->public_id) }}" method="POST" class="bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 rounded-xl p-6 space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Template Display Name</label>
                <input type="text" name="name" x-model="name" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
            </div>

            <div class="flex items-center mt-6">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ $template->is_active ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                    <span class="ml-3 text-sm font-medium text-slate-700 dark:text-slate-300">Template Active</span>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Subject Template</label>
            <input type="text" name="subject_template" x-model="subjectTemplate" required class="mt-1 block w-full font-mono text-sm rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </div>

        <!-- Available Variables Reference -->
        @if(!empty($template->available_variables))
            <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Available Variables</span>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($template->available_variables as $var)
                        <button type="button" @click="insertVar('{{ '{{' . $var . '}}' }}')" class="px-2 py-0.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded text-xs font-mono text-sky-600 dark:text-sky-400 hover:bg-sky-50">
                            &#123;&#123;{{ $var }}&#125;&#125;
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">HTML Template</label>
            <textarea name="body_html_template" x-model="htmlTemplate" rows="8" required class="mt-1 block w-full font-mono text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Plain Text Template (Fallback)</label>
            <textarea name="body_text_template" x-model="textTemplate" rows="5" required class="mt-1 block w-full font-mono text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500"></textarea>
        </div>

        <div class="flex items-center justify-between border-t border-slate-200 dark:border-slate-700 pt-6">
            <button type="button" @click="generatePreview()" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                Live Preview
            </button>

            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 shadow-sm">
                Save Changes
            </button>
        </div>
    </form>

    <!-- Preview Modal -->
    <div x-show="showPreview" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-2xl w-full p-6 space-y-4">
            <div class="flex justify-between items-center border-b pb-3 border-slate-200 dark:border-slate-700">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Rendered Sample Preview</h3>
                <button type="button" @click="showPreview = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase">Subject</span>
                <p class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5" x-text="previewSubject"></p>
            </div>
            <div class="border rounded-lg p-4 bg-slate-50 dark:bg-slate-900/30">
                <div x-html="previewHtml"></div>
            </div>
            <div class="flex justify-end">
                <button type="button" @click="showPreview = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-lg text-sm">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function templateEditor() {
    return {
        name: @json($template->name),
        subjectTemplate: @json($template->subject_template),
        htmlTemplate: @json($template->body_html_template),
        textTemplate: @json($template->body_text_template),
        showPreview: false,
        previewSubject: '',
        previewHtml: '',

        insertVar(varStr) {
            this.htmlTemplate += ' ' + varStr;
        },

        async generatePreview() {
            try {
                const res = await fetch('{{ route("admin.templates.preview", $template->public_id) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        subject_template: this.subjectTemplate,
                        body_html_template: this.htmlTemplate,
                        body_text_template: this.textTemplate,
                    })
                });
                const data = await res.json();
                this.previewSubject = data.subject;
                this.previewHtml = data.html;
                this.showPreview = true;
            } catch (err) {
                alert('Preview failed: ' + err.message);
            }
        }
    }
}
</script>
@endsection
