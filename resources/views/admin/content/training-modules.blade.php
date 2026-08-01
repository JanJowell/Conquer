@extends('admin.layouts.app')

@section('title', 'Training Modules')

@section('content')
@php
    $editingTrainingModuleId = old('_editing_training_module');
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Training Content</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Training Modules</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Manage educational modules for warmups, safety guidance, and structured training programs.</p>
    </div>

    <a href="{{ route('admin.content.training-modules.create') }}" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
        <i class="fas fa-plus mr-2 text-xs"></i>
        Add Training Module
    </a>
</div>

<div class="mb-6 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm sm:p-5">
    <form method="GET" class="grid gap-4 lg:grid-cols-[minmax(220px,1fr)_160px_170px_160px_160px_auto_auto] lg:items-end">
        <div>
            <label for="search" class="mb-2 block text-sm font-medium text-[#111827]">Search modules</label>
            <input
                type="text"
                name="search"
                id="search"
                value="{{ request('search') }}"
                placeholder="Search title, focus, description, or content..."
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
        </div>

        <div>
            <label for="status" class="mb-2 block text-sm font-medium text-[#111827]">Status</label>
            <select id="status" name="status" class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <option value="">All statuses</option>
                <option value="published" @selected(request('status') === 'published')>Published</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </select>
        </div>

        <div>
            <label for="interest_type" class="mb-2 block text-sm font-medium text-[#111827]">Focus</label>
            <select id="interest_type" name="interest_type" class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <option value="">All focuses</option>
                <option value="general" @selected(request('interest_type') === 'general')>General</option>
                @foreach (($interestTypes ?? []) as $interestType)
                    <option value="{{ $interestType }}" @selected(request('interest_type') === $interestType)>{{ $interestType }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="type" class="mb-2 block text-sm font-medium text-[#111827]">Type</label>
            <select id="type" name="type" class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <option value="">All types</option>
                @foreach (['warmup' => 'Warmup', 'safety' => 'Safety', 'guideline' => 'Guideline', 'program' => 'Program'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="difficulty" class="mb-2 block text-sm font-medium text-[#111827]">Difficulty</label>
            <select id="difficulty" name="difficulty" class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <option value="">All levels</option>
                @foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('difficulty') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
            <i class="fas fa-search mr-2 text-xs"></i>
            Filter
        </button>

        <a href="{{ route('admin.content.training-modules') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
            Clear
        </a>
    </form>
</div>

<div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[#eef1f4]">
            <thead class="bg-[#fbfcfd]">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Module</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Type</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Focus</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Difficulty</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Duration</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Status</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f4] bg-white">
                @forelse ($modules as $module)
                    <tr class="align-top">
                        <td class="px-6 py-4">
                            <div class="min-w-[220px]">
                                <p class="text-sm font-semibold text-[#111827]">{{ $module->title }}</p>
                                <p class="mt-1 text-sm leading-6 text-[#6d7685]">{{ \Illuminate\Support\Str::limit($module->description, 100) }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                {{ $module->type === 'warmup' ? 'bg-sky-100 text-sky-800' : '' }}
                                {{ $module->type === 'safety' ? 'bg-rose-100 text-rose-800' : '' }}
                                {{ $module->type === 'guideline' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                {{ $module->type === 'program' ? 'bg-amber-100 text-amber-800' : '' }}">
                                {{ str($module->type)->replace('_', ' ')->title() }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded-full bg-[#eef1f4] px-3 py-1 text-xs font-semibold text-[#4f5a6a]">
                                {{ $module->interest_type ?: 'General' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                {{ $module->difficulty_level === 'beginner' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                {{ $module->difficulty_level === 'intermediate' ? 'bg-amber-100 text-amber-800' : '' }}
                                {{ $module->difficulty_level === 'advanced' ? 'bg-rose-100 text-rose-800' : '' }}">
                                {{ ucfirst($module->difficulty_level) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#202733]">
                            {{ $module->duration ? $module->duration.' min' : 'Not set' }}
                        </td>
                        <td class="px-6 py-4">
                            @if ($module->is_published)
                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                    Published
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                    Draft
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-training-module-view-open="view-training-module-{{ $module->id }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#d9dee7] text-[#315fa8] transition hover:bg-[#f8f9fb]" title="Preview module">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>

                                <button type="button" data-training-module-edit-open="edit-training-module-{{ $module->id }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#d9dee7] text-[#5e6878] transition hover:bg-[#f8f9fb] hover:text-[#202733]" title="Edit module">
                                    <i class="fas fa-pen text-xs"></i>
                                </button>

                                <form method="POST" action="{{ route('admin.content.training-modules.destroy', $module) }}" onsubmit="return confirm('Are you sure you want to delete this training module?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 text-rose-600 transition hover:bg-rose-50" title="Delete module">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-14 text-center">
                            <div class="mx-auto max-w-md rounded-2xl border border-dashed border-[#d9dee7] bg-[#fbfcfd] px-6 py-8">
                                <p class="text-sm font-semibold text-[#202733]">No training modules yet</p>
                                <p class="mt-2 text-sm leading-6 text-[#6d7685]">Create your first module to start publishing warmups, safety content, or training programs.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($modules->hasPages())
        <div class="border-t border-[#eef1f4] px-6 py-4">
            {{ $modules->links() }}
        </div>
    @endif
</div>

@foreach ($modules as $module)
    @php
        $editingThisModule = (string) $editingTrainingModuleId === (string) $module->id;
        $typeClasses = [
            'warmup' => 'bg-sky-100 text-sky-800',
            'safety' => 'bg-rose-100 text-rose-800',
            'guideline' => 'bg-emerald-100 text-emerald-800',
            'program' => 'bg-amber-100 text-amber-800',
        ][$module->type] ?? 'bg-slate-100 text-slate-700';
        $difficultyClasses = [
            'beginner' => 'bg-emerald-100 text-emerald-800',
            'intermediate' => 'bg-amber-100 text-amber-800',
            'advanced' => 'bg-rose-100 text-rose-700',
        ][$module->difficulty_level] ?? 'bg-slate-100 text-slate-700';
    @endphp

    <div id="view-training-module-{{ $module->id }}" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto text-left" role="dialog" aria-modal="true" aria-labelledby="view-training-module-title-{{ $module->id }}">
        <button type="button" data-training-module-view-close class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm" aria-label="Close dialog"></button>

        <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
            <div class="w-full max-w-5xl min-w-0 overflow-hidden rounded-[1.5rem] border border-white/60 bg-[#eaf2f9]/85 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="flex min-w-0 items-start justify-between gap-4 border-b border-white/50 bg-white/40 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Training Module Preview</p>
                        <h2 id="view-training-module-title-{{ $module->id }}" class="mt-2 truncate text-2xl font-semibold tracking-tight text-[#111827]">{{ $module->title }}</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $module->is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $module->is_published ? 'Published' : 'Draft' }}
                            </span>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $typeClasses }}">{{ str($module->type)->replace('_', ' ')->title() }}</span>
                            <span class="inline-flex rounded-full bg-white/60 px-3 py-1 text-xs font-semibold text-[#4f5a6a]">{{ $module->interest_type ?: 'General training' }}</span>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $difficultyClasses }}">{{ ucfirst($module->difficulty_level) }}</span>
                            <span class="inline-flex rounded-full bg-[#eef2ff] px-3 py-1 text-xs font-semibold text-[#315fa8]">{{ $module->duration ? $module->duration.' min' : 'Duration not set' }}</span>
                        </div>
                    </div>
                    <button type="button" data-training-module-view-close class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#6d7685] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#111827]" aria-label="Close dialog">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                    <div class="space-y-5">
                        <section class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Description</p>
                            <p class="mt-3 text-sm leading-7 text-[#202733]">{{ $module->description }}</p>
                        </section>

                        <section class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Content</p>
                            <div class="mt-4 whitespace-pre-line rounded-2xl border border-white/60 bg-white/50 p-4 text-sm leading-7 text-[#202733]">{{ $module->content }}</div>
                        </section>

                        <section class="grid gap-4 md:grid-cols-3">
                            <div class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Training Focus</p>
                                <p class="mt-2 text-sm font-medium text-[#202733]">{{ $module->interest_type ?: 'General training' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Created</p>
                                <p class="mt-2 text-sm font-medium text-[#202733]">{{ $module->created_at?->format('F d, Y h:i A') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Updated</p>
                                <p class="mt-2 text-sm font-medium text-[#202733]">{{ $module->updated_at?->format('F d, Y h:i A') }}</p>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-white/50 bg-white/40 px-6 py-4 backdrop-blur-xl">
                    <button type="button" data-training-module-edit-open="edit-training-module-{{ $module->id }}" data-training-module-view-close class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#1f2937]">
                        Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="edit-training-module-{{ $module->id }}" class="fixed inset-0 z-50 {{ $editingThisModule && $errors->any() ? 'flex' : 'hidden' }} items-start justify-center overflow-y-auto text-left" role="dialog" aria-modal="true" aria-labelledby="edit-training-module-title-{{ $module->id }}">
        <button type="button" data-training-module-edit-close class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm" aria-label="Close dialog"></button>

        <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
            <form method="POST" action="{{ route('admin.content.training-modules.update', $module) }}" class="w-full max-w-5xl min-w-0 overflow-hidden rounded-[1.5rem] border border-white/60 bg-[#eaf2f9]/85 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                @csrf
                @method('PUT')
                <input type="hidden" name="_editing_training_module" value="{{ $module->id }}">

                <div class="flex min-w-0 items-start justify-between gap-4 border-b border-white/50 bg-white/40 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Training Content</p>
                        <h2 id="edit-training-module-title-{{ $module->id }}" class="mt-2 truncate text-2xl font-semibold tracking-tight text-[#111827]">{{ $module->title }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6d7685]">Update the lesson content, settings, and publication status for this module.</p>
                    </div>
                    <button type="button" data-training-module-edit-close class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#6d7685] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#111827]" aria-label="Close dialog">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                    @if ($editingThisModule && $errors->any())
                        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                            Please review the training module details and try again.
                        </div>
                    @endif

                    @include('admin.content.partials.training-module-form', [
                        'module' => $module,
                        'fieldIdPrefix' => 'edit-training-module-'.$module->id.'-',
                        'useOldInput' => $editingThisModule,
                    ])
                </div>

                <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-white/50 bg-white/40 px-6 py-4 backdrop-blur-xl">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#1f2937]">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
@endforeach

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const closeModal = (modal) => {
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');

            if (!document.querySelector('[role="dialog"].flex')) {
                document.body.classList.remove('overflow-hidden');
            }
        };

        const openModal = (modal) => {
            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        };

        document.querySelectorAll('[data-training-module-edit-open]').forEach((button) => {
            button.addEventListener('click', () => openModal(document.getElementById(button.dataset.trainingModuleEditOpen)));
        });

        document.querySelectorAll('[data-training-module-view-open]').forEach((button) => {
            button.addEventListener('click', () => openModal(document.getElementById(button.dataset.trainingModuleViewOpen)));
        });

        document.querySelectorAll('[data-training-module-edit-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(button.closest('[role="dialog"]')));
        });

        document.querySelectorAll('[data-training-module-view-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(button.closest('[role="dialog"]')));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            document.querySelectorAll('[role="dialog"].flex').forEach(closeModal);
        });

        if (document.querySelector('[role="dialog"].flex')) {
            document.body.classList.add('overflow-hidden');
        }
    });
</script>
@endsection
