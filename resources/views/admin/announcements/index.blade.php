@extends('admin.layouts.app')

@section('title', 'Announcements')

@section('content')
    @php
        $canCreateGeneral = ! auth()->user()->managesAssignedEventsOnly();
        $creatingAnnouncement = old('_creating_announcement') === '1' && ! old('_editing_announcement');
    @endphp

    <div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-[1400px] space-y-6">

            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Communications</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-700">Announcements</h1>
                    <p class="mt-1 max-w-2xl text-sm text-slate-400">Publish event updates, schedule changes, and race-day instructions.</p>
                </div>

                <button type="button" data-open-announcement-modal="create-announcement" class="inline-flex items-center justify-center rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    <i class="fas fa-plus mr-2 text-xs"></i>
                    New Announcement
                </button>
            </div>

            <div @class([
                'grid gap-4',
                'md:grid-cols-4' => auth()->user()->managesAssignedEventsOnly(),
                'md:grid-cols-5' => ! auth()->user()->managesAssignedEventsOnly(),
            ])>
                <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <p class="text-sm text-slate-400">Active</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($summary['published']) }}</p>
                </div>
                <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <p class="text-sm text-slate-400">Drafts</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($summary['drafts']) }}</p>
                </div>
                <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <p class="text-sm text-slate-400">Expired</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($summary['expired']) }}</p>
                </div>
                @unless (auth()->user()->managesAssignedEventsOnly())
                    <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                        <p class="text-sm text-slate-400">General</p>
                        <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($summary['general']) }}</p>
                    </div>
                @endunless
                <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <p class="text-sm text-slate-400">Event-specific</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($summary['event_specific']) }}</p>
                </div>
            </div>

            <form method="GET" class="grid gap-3 rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:grid-cols-[220px_minmax(0,1fr)_auto_auto]">
                <div>
                    <label for="status" class="mb-1.5 block text-sm font-medium text-slate-600">Status</label>
                    <select id="status" name="status" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <option value="">All statuses</option>
                        <option value="published" @selected(request('status') === 'published')>Active</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                    </select>
                </div>
                <div>
                    <label for="event_id" class="mb-1.5 block text-sm font-medium text-slate-600">Event</label>
                    <select id="event_id" name="event_id" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <option value="">All events</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="h-11 rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">Filter</button>
                </div>
                <div class="flex items-end">
                    <a href="{{ route('admin.announcements.index') }}" class="inline-flex h-11 items-center rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">Clear</a>
                </div>
            </form>

            <div class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                <th class="px-6 py-4">Announcement</th>
                                <th class="px-6 py-4">Event</th>
                                <th class="px-6 py-4">Published</th>
                                <th class="px-6 py-4">Expires</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($announcements as $announcement)
                                <tr class="align-top transition hover:bg-[#f7f8fc]">
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            @if ($announcement->image_path)
                                                <img src="{{ asset('storage/'.$announcement->image_path) }}" alt="" class="h-14 w-14 shrink-0 rounded-2xl object-cover">
                                            @endif
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="font-medium text-slate-700">{{ $announcement->title }}</p>
                                                    @if ($announcement->is_auto_generated)
                                                        <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-500">Auto</span>
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-xs leading-6 text-slate-400">{{ \Illuminate\Support\Str::limit($announcement->content, 110) }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500">{{ $announcement->event?->title ?: 'General announcement' }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $announcement->published_at?->format('M d, Y h:i A') ?: 'Not published yet' }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $announcement->expires_at?->format('M d, Y h:i A') ?: 'No expiration' }}</td>
                                    <td class="px-6 py-4">
                                        @if ($announcement->is_expired)
                                            <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-500">Expired</span>
                                        @elseif ($announcement->is_published)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-500">Active</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">Draft</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-admin.icon-action label="Edit announcement" icon="fa-pen" data-open-announcement-modal="edit-announcement-{{ $announcement->id }}" />
                                            @if ($announcement->is_published)
                                                <form method="POST" action="{{ route('admin.announcements.unpublish', $announcement) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-full bg-slate-100 px-4 text-xs font-semibold text-slate-500 transition hover:bg-slate-200">
                                                        Unpublish
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.announcements.publish', $announcement) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-full bg-indigo-400 px-4 text-xs font-semibold text-white transition hover:bg-indigo-500">
                                                        Publish
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?');">
                                                @csrf
                                                @method('DELETE')
                                                <x-admin.icon-action type="submit" label="Delete announcement" icon="fa-trash" tone="danger" />
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">No announcements match the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-50 px-6 py-4">
                    {{ $announcements->links() }}
                </div>
            </div>
        </div>
    </div>

    <div id="create-announcement" class="fixed inset-0 z-50 {{ $creatingAnnouncement && $errors->any() ? 'flex' : 'hidden' }} overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="create-announcement-title">
        <button type="button" data-close-announcement-modal class="fixed inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

        <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
            <form method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data" class="w-full max-w-4xl">
                @csrf
                <input type="hidden" name="_creating_announcement" value="1">

                <div class="relative w-full overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">New Announcement</p>
                            <h2 id="create-announcement-title" class="mt-1 text-2xl font-bold text-slate-700">Create Announcement</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Post a public notice for a specific event or share a general operations update.</p>
                        </div>

                        <button type="button" data-close-announcement-modal class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200" aria-label="Close dialog">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                        @if($creatingAnnouncement && $errors->any())
                            <div class="mb-5 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-500">
                                Please review the announcement details and try again.
                            </div>
                        @endif

                        <div class="grid gap-5">
                            <div>
                                <label for="create-announcement-event" class="mb-1.5 block text-sm font-medium text-slate-600">Event</label>
                                <select id="create-announcement-event" name="event_id" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
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
                                    <p class="mt-1.5 text-xs text-slate-400">Event managers must attach announcements to one of their assigned events.</p>
                                @endunless
                                @if($creatingAnnouncement) @error('event_id') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="create-announcement-title-input" class="mb-1.5 block text-sm font-medium text-slate-600">Title</label>
                                <input id="create-announcement-title-input" name="title" type="text" value="{{ old('title') }}" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                @if($creatingAnnouncement) @error('title') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="create-announcement-content" class="mb-1.5 block text-sm font-medium text-slate-600">Content</label>
                                <textarea id="create-announcement-content" name="content" rows="6" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('content') }}</textarea>
                                @if($creatingAnnouncement) @error('content') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>

                            @include('admin.announcements._image-field', [
                                'inputId' => 'create-announcement-image',
                                'imagePath' => null,
                                'showErrors' => $creatingAnnouncement,
                            ])

                            <label class="inline-flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-600">
                                <input type="hidden" name="is_published" value="0">
                                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', true)) class="h-4 w-4 rounded border-0 bg-slate-200 text-indigo-400 focus:ring-2 focus:ring-indigo-200">
                                Publish immediately
                            </label>

                            <div>
                                <label for="create-announcement-expires" class="mb-1.5 block text-sm font-medium text-slate-600">Expires At</label>
                                <input id="create-announcement-expires" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                <p class="mt-1.5 text-xs text-slate-400">Optional. After this date and time, the announcement is hidden from public and mobile views.</p>
                                @if($creatingAnnouncement) @error('expires_at') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-slate-50 bg-white px-6 py-4">
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">
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
            <button type="button" data-close-announcement-modal class="fixed inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
                <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}" enctype="multipart/form-data" class="w-full max-w-5xl">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_announcement" value="{{ $announcement->id }}">

                    <div class="relative w-full max-w-5xl overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Edit Announcement</p>
                                <h2 id="edit-announcement-title-{{ $announcement->id }}" class="mt-1 truncate text-2xl font-bold text-slate-700">{{ $announcement->title }}</h2>
                                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Update the notice content, event assignment, or publishing state.</p>
                            </div>

                            <button type="button" data-close-announcement-modal class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                            @if($editingThisAnnouncement && $errors->any())
                                <div class="mb-5 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-500">
                                    Please review the announcement details and try again.
                                </div>
                            @endif

                            <div class="grid gap-5">
                                <div>
                                    <label for="edit-announcement-event-{{ $announcement->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Event</label>
                                    <select id="edit-announcement-event-{{ $announcement->id }}" name="event_id" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
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
                                        <p class="mt-1.5 text-xs text-slate-400">Event managers must attach announcements to one of their assigned events.</p>
                                    @endunless
                                    @if($editingThisAnnouncement) @error('event_id') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                </div>

                                <div>
                                    <label for="edit-announcement-title-input-{{ $announcement->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Title</label>
                                    <input id="edit-announcement-title-input-{{ $announcement->id }}" name="title" type="text" value="{{ $editingThisAnnouncement ? old('title', $announcement->title) : $announcement->title }}" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                    @if($editingThisAnnouncement) @error('title') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                </div>

                                <div>
                                    <label for="edit-announcement-content-{{ $announcement->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Content</label>
                                    <textarea id="edit-announcement-content-{{ $announcement->id }}" name="content" rows="6" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ $editingThisAnnouncement ? old('content', $announcement->content) : $announcement->content }}</textarea>
                                    @if($editingThisAnnouncement) @error('content') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                </div>

                                @include('admin.announcements._image-field', [
                                    'inputId' => 'edit-announcement-image-'.$announcement->id,
                                    'imagePath' => $announcement->image_path,
                                    'showErrors' => $editingThisAnnouncement,
                                ])

                                <label class="inline-flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-600">
                                    <input type="hidden" name="is_published" value="0">
                                    <input type="checkbox" name="is_published" value="1" @checked($editingThisAnnouncement ? old('is_published', $announcement->is_published) : $announcement->is_published) class="h-4 w-4 rounded border-0 bg-slate-200 text-indigo-400 focus:ring-2 focus:ring-indigo-200">
                                    Published
                                </label>

                                <div>
                                    <label for="edit-announcement-expires-{{ $announcement->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Expires At</label>
                                    <input id="edit-announcement-expires-{{ $announcement->id }}" name="expires_at" type="datetime-local" value="{{ $editingThisAnnouncement ? old('expires_at', $announcement->expires_at?->format('Y-m-d\TH:i')) : $announcement->expires_at?->format('Y-m-d\TH:i') }}" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                    <p class="mt-1.5 text-xs text-slate-400">Optional. After this date and time, the announcement is hidden from public and mobile views.</p>
                                    @if($editingThisAnnouncement) @error('expires_at') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                </div>
                            </div>
                        </div>

                        <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-slate-50 bg-white px-6 py-4">
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                Update Announcement
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    @include('admin.announcements._image-preview-script')

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
