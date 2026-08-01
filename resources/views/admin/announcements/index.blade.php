@extends('admin.layouts.app')

@section('title', 'Announcements')

@section('content')
    @php
        $canCreateGeneral = ! auth()->user()->managesAssignedEventsOnly();
        $creatingAnnouncement = old('_creating_announcement') === '1' && ! old('_editing_announcement');
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Communications</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Announcements</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Publish event updates, schedule changes, and race-day instructions.</p>
            </div>

            <button type="button" data-open-announcement-modal="create-announcement" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                <i class="fas fa-plus mr-2 text-xs"></i>
                New Announcement
            </button>
        </div>

        <div @class([
            'grid gap-4',
            'md:grid-cols-4' => auth()->user()->managesAssignedEventsOnly(),
            'md:grid-cols-5' => ! auth()->user()->managesAssignedEventsOnly(),
        ])>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Active</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['published']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Drafts</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['drafts']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Expired</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['expired']) }}</p>
            </div>
            @unless (auth()->user()->managesAssignedEventsOnly())
                <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-[#6d7685]">General</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['general']) }}</p>
                </div>
            @endunless
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Event-specific</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['event_specific']) }}</p>
            </div>
        </div>

        <form method="GET" class="grid gap-3 rounded-2xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[220px_minmax(0,1fr)_auto_auto]">
            <div>
                <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                <select id="status" name="status" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <option value="">All statuses</option>
                    <option value="published" @selected(request('status') === 'published')>Active</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                </select>
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
            <div class="flex items-end">
                <button type="submit" class="h-11 rounded-xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa] focus:outline-none focus:ring-2 focus:ring-[#d9dee7]">Filter</button>
            </div>
            <div class="flex items-end">
                <a href="{{ route('admin.announcements.index') }}" class="inline-flex h-11 items-center rounded-xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa] focus:outline-none focus:ring-2 focus:ring-[#d9dee7]">Clear</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Announcement</th>
                            <th class="px-6 py-4">Event</th>
                            <th class="px-6 py-4">Published</th>
                            <th class="px-6 py-4">Expires</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($announcements as $announcement)
                            <tr class="align-top">
                                <td class="px-6 py-5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-[#151b26]">{{ $announcement->title }}</p>
                                        @if ($announcement->is_auto_generated)
                                            <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-700">Auto</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs leading-6 text-[#6d7685]">{{ \Illuminate\Support\Str::limit($announcement->content, 110) }}</p>
                                </td>
                                <td class="px-6 py-5">{{ $announcement->event?->title ?: 'General announcement' }}</td>
                                <td class="px-6 py-5">{{ $announcement->published_at?->format('M d, Y h:i A') ?: 'Not published yet' }}</td>
                                <td class="px-6 py-5">{{ $announcement->expires_at?->format('M d, Y h:i A') ?: 'No expiration' }}</td>
                                <td class="px-6 py-5">
                                    @if ($announcement->is_expired)
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Expired</span>
                                    @elseif ($announcement->is_published)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Draft</span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" data-open-announcement-modal="edit-announcement-{{ $announcement->id }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                            Edit
                                        </button>
                                        @if ($announcement->is_published)
                                            <form method="POST" action="{{ route('admin.announcements.unpublish', $announcement) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                                    Unpublish
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.announcements.publish', $announcement) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-[#151b26] px-4 text-xs font-semibold text-white transition hover:bg-[#232b39]">
                                                    Publish
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200 px-4 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-[#6d7685]">No announcements match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $announcements->links() }}
            </div>
        </div>
    </div>

    <div id="create-announcement" class="fixed inset-0 z-50 {{ $creatingAnnouncement && $errors->any() ? 'flex' : 'hidden' }} overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="create-announcement-title">
        <button type="button" data-close-announcement-modal class="fixed inset-0 bg-slate-950/35 backdrop-blur-md" aria-label="Close dialog"></button>

        <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="w-full max-w-4xl">
                @csrf
                <input type="hidden" name="_creating_announcement" value="1">

                <div class="relative w-full overflow-hidden rounded-[1.5rem] border border-white/70 bg-[#f7fbff]/95 shadow-[0_28px_90px_rgba(15,23,42,0.24)] backdrop-blur-2xl ring-1 ring-white/50">
                    <div class="flex items-start justify-between gap-4 border-b border-white/70 bg-white/60 px-6 py-5 backdrop-blur-xl">
                        <div class="min-w-0">
                            <div class="inline-flex items-center gap-2 rounded-full border border-sky-100 bg-sky-50 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-sky-700">
                                <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                                New Announcement
                            </div>
                            <h2 id="create-announcement-title" class="mt-2 text-2xl font-bold tracking-tight text-slate-950">Create Announcement</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Post a public notice for a specific event or share a general operations update.</p>
                        </div>

                        <button type="button" data-close-announcement-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white/80 text-slate-500 shadow-sm transition hover:bg-white hover:text-slate-800" aria-label="Close dialog">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                        @if($creatingAnnouncement && $errors->any())
                            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                                Please review the announcement details and try again.
                            </div>
                        @endif

                        <div class="grid gap-5">
                            <div>
                                <label for="create-announcement-event" class="mb-2 block text-sm font-semibold text-slate-800">Event</label>
                                <select id="create-announcement-event" name="event_id" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                    @if ($canCreateGeneral)
                                        <option value="">General announcement</option>
                                    @else
                                        <option value="">Select an assigned event</option>
                                    @endif
                                    @foreach ($events as $event)
                                        <option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>{{ $event->title }}</option>
                                    @endforeach
                                </select>
                                @unless ($canCreateGeneral)
                                    <p class="mt-2 text-xs text-slate-500">Event managers must attach announcements to one of their assigned events.</p>
                                @endunless
                                @if($creatingAnnouncement) @error('event_id') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="create-announcement-title-input" class="mb-2 block text-sm font-semibold text-slate-800">Title</label>
                                <input id="create-announcement-title-input" name="title" type="text" value="{{ old('title') }}" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                @if($creatingAnnouncement) @error('title') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="create-announcement-content" class="mb-2 block text-sm font-semibold text-slate-800">Content</label>
                                <textarea id="create-announcement-content" name="content" rows="6" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">{{ old('content') }}</textarea>
                                @if($creatingAnnouncement) @error('content') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>

                            <label class="inline-flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm">
                                <input type="hidden" name="is_published" value="0">
                                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', true)) class="h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-950">
                                Publish immediately
                            </label>

                            <div>
                                <label for="create-announcement-expires" class="mb-2 block text-sm font-semibold text-slate-800">Expires At</label>
                                <input id="create-announcement-expires" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                <p class="mt-2 text-xs text-slate-500">Optional. After this date and time, the announcement is hidden from public and mobile views.</p>
                                @if($creatingAnnouncement) @error('expires_at') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-white/70 bg-white/70 px-6 py-4 shadow-sm backdrop-blur-xl">
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-slate-800">
                            Save Announcement
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @foreach ($announcements as $announcement)
        @php
            $editingThisAnnouncement = (string) old('_editing_announcement') === (string) $announcement->id;
        @endphp

        <div id="edit-announcement-{{ $announcement->id }}" class="fixed inset-0 z-50 {{ $editingThisAnnouncement && $errors->any() ? 'flex' : 'hidden' }} overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="edit-announcement-title-{{ $announcement->id }}">
            <button type="button" data-close-announcement-modal class="fixed inset-0 bg-slate-950/35 backdrop-blur-md" aria-label="Close dialog"></button>

            <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
                <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}" class="w-full max-w-5xl">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_announcement" value="{{ $announcement->id }}">

                    <div class="relative w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/60 bg-[#eaf2f9]/85 p-4 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                        <div class="pointer-events-none absolute -top-24 left-10 h-56 w-56 rounded-full bg-sky-300/35 blur-3xl"></div>
                        <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-cyan-300/25 blur-3xl"></div>

                        <div class="relative flex items-start justify-between gap-4 rounded-t-[1.6rem] border border-white/60 bg-white/35 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                            <div class="min-w-0">
                                <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-sky-700 shadow-sm backdrop-blur-xl">
                                    <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                                    Edit Announcement
                                </div>
                                <h2 id="edit-announcement-title-{{ $announcement->id }}" class="mt-2 truncate text-2xl font-bold tracking-tight text-slate-950">{{ $announcement->title }}</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Update the notice content, event assignment, or publishing state.</p>
                            </div>

                            <button type="button" data-close-announcement-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-slate-500 shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-slate-800" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="relative max-h-[calc(100vh-16rem)] overflow-y-auto border-x border-white/60 bg-white/25 px-2 pb-8 pt-4 backdrop-blur-xl sm:px-4">
                            @if($editingThisAnnouncement && $errors->any())
                                <div class="mb-5 rounded-2xl border border-rose-200/70 bg-rose-100/70 px-4 py-3 text-sm font-bold text-rose-700 shadow-sm backdrop-blur-xl">
                                    Please review the announcement details and try again.
                                </div>
                            @endif

                            <section class="rounded-[1.6rem] border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40">
                                <div class="grid gap-5">
                                    <div>
                                        <label for="edit-announcement-event-{{ $announcement->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Event</label>
                                        <select id="edit-announcement-event-{{ $announcement->id }}" name="event_id" class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                            @if ($canCreateGeneral)
                                                <option value="">General announcement</option>
                                            @else
                                                <option value="">Select an assigned event</option>
                                            @endif
                                            @foreach ($events as $event)
                                                <option value="{{ $event->id }}" @selected((string) ($editingThisAnnouncement ? old('event_id', $announcement->event_id) : $announcement->event_id) === (string) $event->id)>{{ $event->title }}</option>
                                            @endforeach
                                        </select>
                                        @unless ($canCreateGeneral)
                                            <p class="mt-2 text-xs text-slate-500">Event managers must attach announcements to one of their assigned events.</p>
                                        @endunless
                                        @if($editingThisAnnouncement) @error('event_id') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-announcement-title-input-{{ $announcement->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Title</label>
                                        <input id="edit-announcement-title-input-{{ $announcement->id }}" name="title" type="text" value="{{ $editingThisAnnouncement ? old('title', $announcement->title) : $announcement->title }}" class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisAnnouncement) @error('title') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-announcement-content-{{ $announcement->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Content</label>
                                        <textarea id="edit-announcement-content-{{ $announcement->id }}" name="content" rows="6" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">{{ $editingThisAnnouncement ? old('content', $announcement->content) : $announcement->content }}</textarea>
                                        @if($editingThisAnnouncement) @error('content') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <label class="inline-flex items-center gap-3 rounded-xl border border-white/60 bg-white/45 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl">
                                        <input type="hidden" name="is_published" value="0">
                                        <input type="checkbox" name="is_published" value="1" @checked($editingThisAnnouncement ? old('is_published', $announcement->is_published) : $announcement->is_published) class="h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-950">
                                        Published
                                    </label>

                                    <div>
                                        <label for="edit-announcement-expires-{{ $announcement->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Expires At</label>
                                        <input id="edit-announcement-expires-{{ $announcement->id }}" name="expires_at" type="datetime-local" value="{{ $editingThisAnnouncement ? old('expires_at', $announcement->expires_at?->format('Y-m-d\TH:i')) : $announcement->expires_at?->format('Y-m-d\TH:i') }}" class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        <p class="mt-2 text-xs text-slate-500">Optional. After this date and time, the announcement is hidden from public and mobile views.</p>
                                        @if($editingThisAnnouncement) @error('expires_at') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 rounded-b-[1.6rem] border border-t border-white/60 bg-white/40 px-6 py-4 shadow-sm backdrop-blur-xl">
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950/90 px-5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800">
                                Update Announcement
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <script>
        (function () {
            const closeModal = (modal) => {
                if (!modal) {
                    return;
                }

                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            };

            const openModal = (modal) => {
                if (!modal) {
                    return;
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            };

            document.querySelectorAll('[data-open-announcement-modal]').forEach((button) => {
                button.addEventListener('click', () => openModal(document.getElementById(button.dataset.openAnnouncementModal)));
            });

            document.querySelectorAll('[data-close-announcement-modal]').forEach((button) => {
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
        })();
    </script>
@endsection
