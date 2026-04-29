@extends('admin.layouts.app')

@section('title', 'Training Modules')

@section('content')
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

<div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[#eef1f4]">
            <thead class="bg-[#fbfcfd]">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Module</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Type</th>
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
                                <a href="{{ route('admin.content.training-modules.edit', $module) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#d9dee7] text-[#5e6878] transition hover:bg-[#f8f9fb] hover:text-[#202733]" title="Edit module">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>

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
                        <td colspan="6" class="px-6 py-14 text-center">
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
@endsection
