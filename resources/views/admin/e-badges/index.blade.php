@extends('admin.layouts.app')

@section('title', 'E-Badges')

@section('content')
<style>
    .ebadge-glass-pagination nav > div:first-child {
        color: #64748b;
    }

    .ebadge-glass-pagination nav > div:last-child {
        align-items: center;
        gap: 0.75rem;
    }

    .ebadge-glass-pagination nav p {
        color: #64748b;
        font-size: 0.875rem;
    }

    .ebadge-glass-pagination nav span[aria-current="page"] span,
    .ebadge-glass-pagination nav a,
    .ebadge-glass-pagination nav span[aria-disabled="true"] span {
        border-radius: 0.875rem !important;
        border: 1px solid rgba(255, 255, 255, 0.6) !important;
        background: rgba(255, 255, 255, 0.45) !important;
        box-shadow: 0 10px 24px rgba(148, 163, 184, 0.18) !important;
        backdrop-filter: blur(18px);
    }

    .ebadge-glass-pagination nav span[aria-current="page"] span {
        background: rgba(15, 23, 42, 0.92) !important;
        border-color: rgba(15, 23, 42, 0.92) !important;
        color: #ffffff !important;
    }

    .ebadge-glass-pagination nav a {
        color: #202733 !important;
        transition: background 160ms ease, transform 160ms ease;
    }

    .ebadge-glass-pagination nav a:hover {
        background: rgba(255, 255, 255, 0.72) !important;
        transform: translateY(-1px);
    }

    .ebadge-glass-pagination nav span[aria-disabled="true"] span {
        color: #94a3b8 !important;
        opacity: 0.75;
    }
</style>

    @php
        $autoIssueRuleDescriptions = [
            'manual' => 'Admin chooses this badge manually from Results. Use this for special awards, volunteers, or exceptions.',
            'completed_event' => 'Automatically issues to completed participants with a saved race result. Scope it to an event or category when needed.',
            'first_overall' => 'Automatically issues only to the participant ranked #1 overall after results are recalculated.',
            'second_overall' => 'Automatically issues only to the participant ranked #2 overall after results are recalculated.',
            'third_overall' => 'Automatically issues only to the participant ranked #3 overall after results are recalculated.',
            'top_3_overall' => 'Automatically issues to participants ranked #1, #2, or #3 overall.',
            'first_category' => 'Automatically issues only to the participant ranked #1 in the selected category.',
            'second_category' => 'Automatically issues only to the participant ranked #2 in the selected category.',
            'third_category' => 'Automatically issues only to the participant ranked #3 in the selected category.',
            'top_3_category' => 'Automatically issues to participants ranked #1, #2, or #3 in their category.',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Recognition</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">E-Badges</h1>
                <p class="mt-2 max-w-3xl text-sm text-[#6d7685]">Create badge templates, then issue them to completed participants from Results.</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Templates</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['templates']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Active</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['active_templates']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Issued</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['issued']) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.e-badges.store') }}" enctype="multipart/form-data" class="grid gap-4 rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm lg:grid-cols-2">
            @csrf
            <div>
                <label for="title" class="mb-2 block text-sm font-medium text-[#3d4757]">Badge Title</label>
                <input id="title" name="title" value="{{ old('title') }}" type="text" required
                    class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
            </div>

            <div>
                <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                <select id="event_id" name="event_id" data-ebadge-event-select class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    @unless (auth()->user()->managesAssignedEventsOnly())
                        <option value="">General badge</option>
                    @endunless
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) old('event_id', request('event_id')) === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-[#6d7685]">Choose an event to limit this badge to one race. General badges can be issued across events.</p>
            </div>

            <div>
                <label for="category_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Category</label>
                <select id="category_id" name="category_id" data-ebadge-category-select class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <option value="">All categories for selected event</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" data-event-id="{{ $category->event_id }}" @selected((string) old('category_id') === (string) $category->id)>
                            {{ $category->event?->title }} - {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-[#6d7685]">Optional. Select a category for distance-specific badges or category rank rules.</p>
            </div>

            <div data-criteria-field>
                <label for="criteria" class="mb-2 block text-sm font-medium text-[#3d4757]">Criteria Text</label>
                <input id="criteria" name="criteria" value="{{ old('criteria') }}" type="text" placeholder="Completed 10K, Finisher, Volunteer"
                    class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <p class="mt-2 text-xs text-[#6d7685]">Shown in the mobile app for manually awarded badges.</p>
            </div>

            <div>
                <label for="auto_issue_rule" class="mb-2 block text-sm font-medium text-[#3d4757]">Auto Issue Rule</label>
                <select id="auto_issue_rule" name="auto_issue_rule" data-auto-issue-rule-select class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    @foreach ($autoIssueRules as $rule => $label)
                        <option value="{{ $rule }}" @selected(old('auto_issue_rule', 'manual') === $rule)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-[#6d7685]" data-auto-issue-rule-help>{{ $autoIssueRuleDescriptions[old('auto_issue_rule', 'manual')] ?? '' }}</p>
            </div>

            <div class="lg:col-span-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                Automatic badges are issued after results are saved or updated. Overlapping rules can intentionally award multiple badges; exact duplicate templates are blocked when saving.
            </div>

            <div>
                <label for="image_upload" class="mb-2 block text-sm font-medium text-[#3d4757]">Badge Image</label>
                <input id="image_upload" name="image_upload" type="file" accept="image/*"
                    class="block h-11 w-full rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm text-[#151b26] file:mr-3 file:rounded-lg file:border-0 file:bg-[#eef1f4] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#151b26]">
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="mb-2 block text-sm font-medium text-[#3d4757]">Description</label>
                <textarea id="description" name="description" rows="3"
                    class="w-full rounded-xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">{{ old('description') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <input id="is_active" name="is_active" type="checkbox" value="1" checked class="h-4 w-4 rounded border-[#d9dee7] text-[#151b26]">
                <label for="is_active" class="text-sm font-medium text-[#3d4757]">Active</label>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#151b26] px-5 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                    Create E-Badge
                </button>
            </div>
        </form>

        <form method="GET" class="grid gap-3 rounded-2xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[260px_auto]">
            <div>
                <label for="filter_event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                <select id="filter_event_id" name="event_id" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Filter</button>
                <a href="{{ route('admin.e-badges.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#6d7685] transition hover:bg-[#f7f8fa]">
                    Clear
                </a>
            </div>
        </form>

        <div class="flex flex-wrap gap-2 rounded-2xl border border-[#d9dee7] bg-white p-2 shadow-sm" role="tablist" aria-label="E-badge views">
            <button type="button" data-ebadge-tab-button="templates" class="inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition">
                Templates
            </button>
            <button type="button" data-ebadge-tab-button="issued" class="inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition">
                Issued Badges
            </button>
        </div>

        <div data-ebadge-tab-panel="templates" class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="border-b border-[#eef1f4] px-6 py-4">
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Badge Templates</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Badge</th>
                            <th class="px-6 py-4">Event / Category</th>
                            <th class="px-6 py-4">Criteria / Rule</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Issued</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($badges as $badge)
                            <tr class="align-top">
                                <td class="px-6 py-5">
                                    <div class="flex gap-3">
                                        @if ($badge->image_path)
                                            <button type="button"
                                                class="block h-14 w-14 overflow-hidden rounded-xl border border-[#d9dee7] bg-white transition hover:border-[#aeb7c3] focus:outline-none focus:ring-2 focus:ring-[#151b26]/20"
                                                aria-label="Preview {{ $badge->title }} e-badge"
                                                data-ebadge-preview-open
                                                data-preview-src="{{ asset('storage/'.$badge->image_path) }}"
                                                data-preview-title="{{ $badge->title }}">
                                                <img src="{{ asset('storage/'.$badge->image_path) }}" alt="{{ $badge->title }}" class="h-full w-full object-cover">
                                            </button>
                                        @else
                                            <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-[#d9dee7] bg-[#f4f6f8] text-[#6d7685]">
                                                <i class="fas fa-award"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-[#151b26]">{{ $badge->title }}</p>
                                            <p class="mt-1 max-w-md text-xs leading-5 text-[#6d7685]">{{ $badge->description ?: 'No description provided' }}</p>
                                            @unless ($badge->image_path)
                                                <span class="mt-2 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                                    No image uploaded
                                                </span>
                                            @endunless
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <p>{{ $badge->event?->title ?: 'General' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $badge->category?->name ?: 'All categories' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p>{{ $badge->criteria ?: ($autoIssueRules[$badge->auto_issue_rule] ?? 'Manual only') }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ ($badge->auto_issue_rule ?? 'manual') === 'manual' ? 'Manual criteria text' : 'Automatic issue rule' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $badge->is_active ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'border border-slate-200 bg-slate-50 text-slate-600' }}">
                                        {{ $badge->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-5">{{ number_format($badge->issued_badges_count) }}</td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" data-ebadge-edit-open="edit-ebadge-{{ $badge->id }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                            Edit
                                        </button>
                                        @if ($badge->issued_badges_count === 0)
                                            <form method="POST" action="{{ route('admin.e-badges.destroy', $badge) }}" onsubmit="return confirm('Delete this e-badge template?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200 px-4 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                                    Delete
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-xs font-semibold text-[#8a93a1]" title="Issued badge templates cannot be deleted.">
                                                Delete locked
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-[#6d7685]">No e-badge templates yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ebadge-glass-pagination border-t border-white/60 bg-white/30 px-6 py-4 backdrop-blur-xl">
                {{ $badges->links() }}
            </div>
        </div>

        @foreach ($badges as $badge)
            <div id="edit-ebadge-{{ $badge->id }}" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto text-left" role="dialog" aria-modal="true" aria-labelledby="edit-ebadge-title-{{ $badge->id }}">
                <button type="button" data-ebadge-edit-close class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm" aria-label="Close dialog"></button>

                <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
                    <form method="POST" action="{{ route('admin.e-badges.update', $badge) }}" enctype="multipart/form-data" class="w-full max-w-5xl min-w-0 overflow-hidden rounded-[1.5rem] border border-white/60 bg-[#eaf2f9]/85 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="image_path" value="{{ $badge->image_path }}">

                        <div class="flex min-w-0 items-start justify-between gap-4 border-b border-white/50 bg-white/40 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">E-Badge Template</p>
                                <h2 id="edit-ebadge-title-{{ $badge->id }}" class="mt-2 truncate text-2xl font-semibold tracking-tight text-[#151b26]">{{ $badge->title }}</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6d7685]">Update the badge details, image, event scope, and issuing rule.</p>
                            </div>
                            <button type="button" data-ebadge-edit-close class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#6d7685] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#151b26]" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                            <div class="grid min-w-0 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Title</label>
                                    <input name="title" value="{{ $badge->title }}" type="text" required class="h-11 w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                                    <select name="event_id" data-ebadge-event-select class="h-11 w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                        @unless (auth()->user()->managesAssignedEventsOnly())
                                            <option value="">General badge</option>
                                        @endunless
                                        @foreach ($events as $event)
                                            <option value="{{ $event->id }}" @selected((int) $badge->event_id === (int) $event->id)>{{ $event->title }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-2 text-xs text-[#6d7685]">Choose an event to limit this badge to one race.</p>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Category</label>
                                    <select name="category_id" data-ebadge-category-select class="h-11 w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                        <option value="">All categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" data-event-id="{{ $category->event_id }}" @selected((int) $badge->category_id === (int) $category->id)>
                                                {{ $category->event?->title }} - {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-2 text-xs text-[#6d7685]">Optional. Use for distance-specific badges or category rank rules.</p>
                                </div>
                                <div data-criteria-field>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Criteria Text</label>
                                    <input name="criteria" value="{{ $badge->criteria }}" type="text" class="h-11 w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                    <p class="mt-2 text-xs text-[#6d7685]">Shown in the mobile app for manually awarded badges.</p>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Auto Issue Rule</label>
                                    <select name="auto_issue_rule" data-auto-issue-rule-select class="h-11 w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                        @foreach ($autoIssueRules as $rule => $label)
                                            <option value="{{ $rule }}" @selected(($badge->auto_issue_rule ?? 'manual') === $rule)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-2 text-xs text-[#6d7685]" data-auto-issue-rule-help>{{ $autoIssueRuleDescriptions[$badge->auto_issue_rule ?? 'manual'] ?? '' }}</p>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Image</label>
                                    <input name="image_upload" type="file" accept="image/*" class="block h-11 w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-3 py-2 text-sm text-[#151b26] shadow-sm backdrop-blur-xl file:mr-3 file:rounded-lg file:border-0 file:bg-[#eef1f4] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#151b26]">
                                    @unless ($badge->image_path)
                                        <p class="mt-2 text-xs font-semibold text-amber-700">No image uploaded. Add one so this badge looks complete in the mobile app.</p>
                                    @endunless
                                </div>
                                <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm leading-6 text-amber-900 md:col-span-2">
                                    Automatic badges update after results are saved. Exact duplicate templates are blocked, but overlapping rules may award multiple badges intentionally.
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Description</label>
                                    <textarea name="description" rows="3" class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">{{ $badge->description }}</textarea>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input id="badge-active-{{ $badge->id }}" name="is_active" type="checkbox" value="1" @checked($badge->is_active) class="h-4 w-4 rounded border-[#d9dee7] text-[#151b26]">
                                    <label for="badge-active-{{ $badge->id }}" class="text-sm font-medium text-[#3d4757]">Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-white/50 bg-white/40 px-6 py-4 backdrop-blur-xl">
                            <button type="button" data-ebadge-edit-close class="inline-flex h-11 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-5 text-sm font-semibold text-[#151b26] shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                                Cancel
                            </button>
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#151b26] px-5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#232b39]">
                                Save E-Badge
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        <div data-ebadge-tab-panel="issued" class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="border-b border-[#eef1f4] px-6 py-4">
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Issued E-Badges</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Participant</th>
                            <th class="px-6 py-4">Badge</th>
                            <th class="px-6 py-4">Event / Category</th>
                            <th class="px-6 py-4">Issued</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($issuedBadges as $issuedBadge)
                            <tr>
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $issuedBadge->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">Bib {{ $issuedBadge->registration?->bib_number ?: 'not assigned' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $issuedBadge->badge?->title ?: 'Removed badge' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $issuedBadge->notes ?: 'No notes' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p>{{ $issuedBadge->event?->title ?: 'Deleted event' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $issuedBadge->registration?->category?->name ?: 'No category' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p>{{ $issuedBadge->issued_at?->format('M d, Y g:i A') }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $issuedBadge->issuer?->name ?: 'System' }}</p>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <form method="POST" action="{{ route('admin.e-badges.revoke', $issuedBadge) }}" onsubmit="return confirm('Revoke this issued e-badge?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200 px-4 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                            Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-[#6d7685]">No e-badges have been issued yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ebadge-glass-pagination border-t border-white/60 bg-white/30 px-6 py-4 backdrop-blur-xl">
                {{ $issuedBadges->links() }}
            </div>
        </div>

        <dialog data-ebadge-preview-dialog class="w-full max-w-4xl min-w-0 overflow-hidden rounded-[1.5rem] border border-white/60 bg-[#eaf2f9]/85 p-0 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop:bg-slate-950/40 backdrop:backdrop-blur-sm backdrop-blur-2xl ring-1 ring-white/40">
            <div class="flex min-w-0 items-start justify-between gap-4 border-b border-white/50 bg-white/40 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">E-Badge Preview</p>
                    <h2 class="mt-2 truncate text-2xl font-semibold tracking-tight text-[#151b26]" data-ebadge-preview-title>E-Badge preview</h2>
                </div>
                <button type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#6d7685] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#151b26]" data-ebadge-preview-close aria-label="Close dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="max-h-[calc(100vh-12rem)] overflow-y-auto px-6 py-5">
                <div class="rounded-2xl border border-white/60 bg-white/35 p-4 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
                    <img src="" alt="E-badge preview" class="mx-auto max-h-[calc(100vh-16rem)] w-full min-w-0 object-contain" data-ebadge-preview-image>
                </div>
            </div>
        </dialog>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const autoIssueRuleDescriptions = @json($autoIssueRuleDescriptions);
            const dialog = document.querySelector('[data-ebadge-preview-dialog]');
            const image = document.querySelector('[data-ebadge-preview-image]');
            const title = document.querySelector('[data-ebadge-preview-title]');
            const closeButton = document.querySelector('[data-ebadge-preview-close]');

            if (!dialog || !image || !title || !closeButton) {
                return;
            }

            const closeEditModal = (modal) => {
                if (!modal) {
                    return;
                }

                modal.classList.add('hidden');
                modal.classList.remove('flex');

                if (!document.querySelector('[role="dialog"].flex')) {
                    document.body.classList.remove('overflow-hidden');
                }
            };

            const openEditModal = (modal) => {
                if (!modal) {
                    return;
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            };

            document.querySelectorAll('[data-ebadge-edit-open]').forEach((button) => {
                button.addEventListener('click', () => openEditModal(document.getElementById(button.dataset.ebadgeEditOpen)));
            });

            document.querySelectorAll('[data-ebadge-edit-close]').forEach((button) => {
                button.addEventListener('click', () => closeEditModal(button.closest('[role="dialog"]')));
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                document.querySelectorAll('[role="dialog"].flex').forEach(closeEditModal);
            });

            document.querySelectorAll('[data-ebadge-preview-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    image.src = button.dataset.previewSrc;
                    image.alt = `${button.dataset.previewTitle} e-badge preview`;
                    title.textContent = button.dataset.previewTitle;
                    document.body.classList.add('overflow-hidden');
                    dialog.showModal();
                });
            });

            closeButton.addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
            dialog.addEventListener('close', () => {
                if (!document.querySelector('[role="dialog"].flex')) {
                    document.body.classList.remove('overflow-hidden');
                }
            });

            document.querySelectorAll('[data-ebadge-event-select]').forEach((eventSelect) => {
                const form = eventSelect.closest('form');
                const categorySelect = form?.querySelector('[data-ebadge-category-select]');

                if (!categorySelect) {
                    return;
                }

                const syncCategories = () => {
                    const eventId = eventSelect.value;
                    const selectedOption = categorySelect.selectedOptions[0];

                    categorySelect.querySelectorAll('option[data-event-id]').forEach((option) => {
                        option.hidden = !eventId || option.dataset.eventId !== eventId;
                    });

                    if (selectedOption?.hidden) {
                        categorySelect.value = '';
                    }
                };

                eventSelect.addEventListener('change', syncCategories);
                syncCategories();
            });

            document.querySelectorAll('[data-auto-issue-rule-select]').forEach((ruleSelect) => {
                const form = ruleSelect.closest('form');
                const criteriaField = form?.querySelector('[data-criteria-field]');
                const criteriaInput = criteriaField?.querySelector('[name="criteria"]');
                const ruleHelp = form?.querySelector('[data-auto-issue-rule-help]');

                if (!criteriaField || !criteriaInput) {
                    return;
                }

                const syncCriteriaField = () => {
                    const isManual = ruleSelect.value === 'manual';
                    criteriaField.hidden = !isManual;
                    criteriaInput.disabled = !isManual;

                    if (!isManual) {
                        criteriaInput.value = '';
                    }

                    if (ruleHelp) {
                        ruleHelp.textContent = autoIssueRuleDescriptions[ruleSelect.value] || '';
                    }
                };

                ruleSelect.addEventListener('change', syncCriteriaField);
                syncCriteriaField();
            });

            const tabButtons = document.querySelectorAll('[data-ebadge-tab-button]');
            const tabPanels = document.querySelectorAll('[data-ebadge-tab-panel]');

            const activateTab = (tab) => {
                tabButtons.forEach((button) => {
                    const isActive = button.dataset.ebadgeTabButton === tab;
                    button.classList.toggle('bg-[#151b26]', isActive);
                    button.classList.toggle('text-white', isActive);
                    button.classList.toggle('text-[#5f6b7a]', !isActive);
                    button.classList.toggle('hover:bg-[#f7f8fa]', !isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                tabPanels.forEach((panel) => {
                    panel.hidden = panel.dataset.ebadgeTabPanel !== tab;
                });

                if (history.replaceState) {
                    history.replaceState(null, '', `${location.pathname}${location.search}#${tab}`);
                }
            };

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => activateTab(button.dataset.ebadgeTabButton));
            });

            const initialTab = location.hash === '#issued' || new URLSearchParams(location.search).has('issued_page')
                ? 'issued'
                : 'templates';

            activateTab(initialTab);
        });
    </script>
@endsection
