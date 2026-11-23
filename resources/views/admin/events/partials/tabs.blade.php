@php
    $commonEventFilters = request()->only(['search', 'interest_type']);
    $eventFilters = $archiveFilter === 'active'
        ? request()->only(['search', 'interest_type', 'status', 'review_status'])
        : $commonEventFilters;
@endphp
<nav aria-label="Event sections" class="flex flex-wrap gap-2 border-b border-[#d9dee7] pb-3">
    <a href="{{ route('admin.events.index', [...$eventFilters, 'archive' => 'active']) }}" @if ($archiveFilter === 'active') aria-current="page" @endif
        class="rounded-xl px-4 py-2 text-sm font-semibold {{ $archiveFilter === 'active' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Events</a>
    <a href="{{ route('admin.events.index', [...$commonEventFilters, 'archive' => 'archived']) }}" @if ($archiveFilter === 'archived') aria-current="page" @endif
        class="rounded-xl px-4 py-2 text-sm font-semibold {{ $archiveFilter === 'archived' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Archived Events</a>
</nav>
