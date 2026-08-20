@extends('admin.layouts.app')

@section('title', 'Results')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Race Results</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Results Management</h1>
            <p class="mt-2 max-w-3xl text-sm text-[#6d7685]">Encode finish times, automatically rank finishers, and keep e-badges aligned with the latest results.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Published Results</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['published_results']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Awaiting Results</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['awaiting_results']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Completed Registrations</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['completed_registrations']) }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-900">
            The Finish button calculates elapsed time from the participant category's recorded start. Saving or updating a result recalculates rankings and automatic e-badges.
        </div>

        <section class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="border-b border-[#eef1f4] px-5 py-4">
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Category Starts</h2>
                <p class="mt-1 text-sm text-[#6d7685]">Start each race wave once. Railway server time becomes the official start for all participants in that category.</p>
            </div>
            <div class="divide-y divide-[#eef1f4]">
                @forelse ($raceCategories as $raceCategory)
                    @php
                        $scheduledStartAt = $raceCategory->scheduledStartAt();
                    @endphp
                    <div class="grid gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_130px_220px] md:items-center">
                        <div>
                            <p class="font-semibold text-[#151b26]">{{ $raceCategory->event?->title }} · {{ $raceCategory->name }}</p>
                            <p class="mt-1 text-xs text-[#6d7685]">{{ number_format($raceCategory->checked_in_count) }} checked-in/completed · {{ number_format($raceCategory->race_results_count) }} results</p>
                            <p class="mt-1 text-xs font-medium text-[#4f5a6a]">Scheduled gun start {{ $scheduledStartAt?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                            <p class="mt-1 text-xs text-[#6d7685]">Cutoff/end {{ $raceCategory->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                        </div>
                        <div>
                            @if ($raceCategory->started_at)
                                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">In Progress</span>
                            @else
                                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Not Started</span>
                            @endif
                        </div>
                        <div class="md:text-right">
                            @if ($raceCategory->started_at)
                                <p class="text-sm font-semibold text-[#151b26]">{{ $raceCategory->started_at->format('M j, Y g:i:s A') }}</p>
                                <p class="mt-1 text-xs text-[#6d7685]">Started by {{ $raceCategory->startedBy?->name ?: 'administrator' }}</p>
                            @elseif ($raceCategory->status === 'draft')
                                <p class="text-xs text-[#6d7685]">Open or close this category before starting it.</p>
                            @elseif (! $scheduledStartAt)
                                <p class="text-xs font-medium text-amber-700">Set the event schedule before starting.</p>
                            @elseif (now()->lt($scheduledStartAt))
                                <p class="text-xs font-medium text-sky-700">Start available {{ $scheduledStartAt->format('M j, Y g:i A') }}</p>
                            @else
                                <form method="POST" action="{{ route('admin.categories.start', $raceCategory) }}" onsubmit="return confirm('Start this category now? This server timestamp will be used for every participant finish calculation and cannot be restarted.');">
                                    @csrf
                                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                        Start Category
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-[#6d7685]">Checked-in categories will appear here. You can also start a category from its management page.</div>
                @endforelse
            </div>
        </section>

        <form method="GET" class="grid gap-3 rounded-2xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_220px_auto]">
            <div>
                <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Participant, bib, event"
                    class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
            </div>
            <div>
                <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                <select id="event_id" name="event_id" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa] focus:outline-none focus:ring-2 focus:ring-[#d9dee7]">Filter</button>
                <a href="{{ route('admin.results.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#6d7685] transition hover:bg-[#f7f8fa] focus:outline-none focus:ring-2 focus:ring-[#d9dee7]">
                    Clear
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Participant</th>
                            <th class="px-6 py-4">Event / Category</th>
                            <th class="px-6 py-4">Bib</th>
                            <th class="px-4 py-4">Status</th>
                            <th class="px-4 py-4">Finish Time</th>
                            <th class="px-4 py-4">Ranks</th>
                            <th class="px-4 py-4">Remarks</th>
                            <th class="px-4 py-4">E-Badges</th>
                            <th class="px-6 py-4 text-right">Save</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($registrations as $registration)
                            @php($result = $registration->raceResult)
                            @php($formId = 'result-form-'.$registration->id)
                            @php($isOldRow = (string) old('result_row_id') === (string) $registration->id)
                            <tr class="align-top">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $registration->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $registration->user?->email ?: 'No email available' }}</p>
                                    <form id="{{ $formId }}" method="POST" action="{{ $result ? route('admin.results.update', $result) : route('admin.results.store') }}" @if ($result) data-confirm-update="true" @endif>
                                        @csrf
                                        <input type="hidden" name="result_row_id" value="{{ $registration->id }}">
                                        @if ($result)
                                            @method('PATCH')
                                        @else
                                            <input type="hidden" name="registration_id" value="{{ $registration->id }}">
                                        @endif
                                    </form>
                                </td>
                                <td class="px-6 py-5">
                                    <p>{{ $registration->event?->title ?: 'Deleted event' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">Registered: {{ $registration->category?->name ?: 'No category' }}</p>
                                    @if ($registration->category?->started_at)
                                        <p class="mt-1 text-xs font-medium text-emerald-700">Started {{ $registration->category->started_at->format('g:i:s A') }}</p>
                                    @else
                                        <p class="mt-1 text-xs font-medium text-amber-700">Category not started</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $registration->bib_number ?: 'Not assigned' }}</td>
                                <td class="px-4 py-5">
                                    @if ($result)
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Published</span>
                                    @elseif ($registration->status === 'completed')
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Completed</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Awaiting result</span>
                                    @endif
                                </td>
                                <td class="px-4 py-5">
                                    <div class="flex items-center gap-2">
                                        <input form="{{ $formId }}" name="finish_time" type="text" value="{{ $isOldRow ? old('finish_time') : $result?->finish_time }}" placeholder="00:45:12"
                                            class="h-10 w-32 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                        @if ($result)
                                            <button form="{{ $formId }}" type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                                                Update
                                            </button>
                                        @else
                                            <button form="{{ $formId }}" name="finish_now" value="1" type="submit" @disabled(! $registration->category?->started_at)
                                                title="{{ $registration->category?->started_at ? 'Calculate from category start' : 'Start this category first' }}"
                                                class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400">
                                                Finish
                                            </button>
                                        @endif
                                    </div>
                                    <p class="mt-2 text-xs leading-5 text-[#6d7685]">Use MM:SS or HH:MM:SS.</p>
                                </td>
                                <td class="px-4 py-5">
                                    <div class="grid w-40 gap-2">
                                        <div class="rounded-xl border border-[#d9dee7] bg-[#f8fafc] px-3 py-2">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#7a8495]">Overall</p>
                                            <p class="mt-1 text-base font-semibold text-[#151b26]">{{ $result?->rank_overall ? '#'.$result->rank_overall : 'Pending' }}</p>
                                        </div>
                                        <div class="rounded-xl border border-[#d9dee7] bg-[#f8fafc] px-3 py-2">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#7a8495]">Category</p>
                                            <p class="mt-1 text-base font-semibold text-[#151b26]">{{ $result?->rank_category ? '#'.$result->rank_category : 'Pending' }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs leading-5 text-[#6d7685]">Ranks update after save.</p>
                                </td>
                                <td class="px-4 py-5">
                                    <input form="{{ $formId }}" name="remarks" type="text" value="{{ $isOldRow ? old('remarks') : $result?->remarks }}" placeholder="Optional notes"
                                        class="h-10 w-56 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                </td>
                                <td class="px-4 py-5">
                                    <div class="w-72 space-y-3">
                                        @if ($registration->issuedEBadges->isNotEmpty())
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($registration->issuedEBadges as $issuedBadge)
                                                    @php($isAutomaticBadge = $issuedBadge->issued_by === null && $issuedBadge->notes === 'Automatically issued')
                                                    <span class="inline-flex items-center gap-1.5 rounded-full border {{ $isAutomaticBadge ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700' }} px-3 py-1 text-xs font-semibold">
                                                        <span>{{ $issuedBadge->badge?->title ?: 'Removed badge' }}</span>
                                                        <span class="rounded-full bg-white/70 px-1.5 py-0.5 text-[10px] uppercase tracking-[0.12em]">{{ $isAutomaticBadge ? 'Auto' : 'Manual' }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-xs text-[#6d7685]">No badges issued yet.</p>
                                        @endif

                                        @if ($registration->status === 'completed')
                                            @php($rowBadges = $badges->filter(fn ($badge) => ($badge->auto_issue_rule ?? 'manual') === 'manual' && ($badge->event_id === null || (int) $badge->event_id === (int) $registration->event_id) && ($badge->category_id === null || (int) $badge->category_id === (int) $registration->category_id)))
                                            @if ($rowBadges->isNotEmpty())
                                                <form method="POST" action="{{ route('admin.e-badges.issue', $registration) }}" class="grid gap-2">
                                                    @csrf
                                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#7a8495]">Manual exception</p>
                                                    <select name="e_badge_id" class="h-10 w-full rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                                        <option value="">Choose badge</option>
                                                        @foreach ($rowBadges as $badge)
                                                            <option value="{{ $badge->id }}">{{ $badge->title }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="flex gap-2">
                                                        <input name="notes" type="text" placeholder="Optional note"
                                                            class="h-10 min-w-0 flex-1 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-3 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                                            Issue
                                                        </button>
                                                    </div>
                                                    <p class="text-xs leading-5 text-[#6d7685]">Use manual issue for exceptions. Rank-based badges are handled automatically.</p>
                                                </form>
                                            @else
                                                <a href="{{ route('admin.e-badges.index', ['event_id' => $registration->event_id]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-3 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                                    Add manual badge
                                                </a>
                                            @endif
                                        @else
                                            <p class="text-xs text-[#6d7685]">Available after completion.</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <button form="{{ $formId }}" type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-[#151b26] px-4 text-xs font-semibold text-white transition hover:bg-[#232b39] focus:outline-none focus:ring-2 focus:ring-[#151b26]/30">
                                        Save
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-[#6d7685]">No registrations are ready for result encoding yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $registrations->links() }}
            </div>
        </div>
    </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('form[data-confirm-update="true"]').forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        if (!confirm('Updating this finish time will recalculate rankings for this event. Continue?')) {
                            event.preventDefault();
                        }
                    });
                });
            });
        </script>
@endsection
