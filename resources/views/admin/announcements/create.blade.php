@extends('admin.layouts.app')

@section('title', 'Create Announcement')

@section('content')
    <div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl space-y-6">

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Communications</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-700">Create Announcement</h1>
                <p class="mt-1 text-sm text-slate-400">Post a public notice for a specific event or share a general operations update.</p>
            </div>

            @if ($errors->any())
                <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-500">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data" class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                @csrf

                <div class="grid gap-5">
                    <div>
                        <label for="event_id" class="mb-1.5 block text-sm font-medium text-slate-600">Event</label>
                        <select id="event_id" name="event_id" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
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
                    </div>

                    <div>
                        <label for="title" class="mb-1.5 block text-sm font-medium text-slate-600">Title</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    </div>

                    <div>
                        <label for="content" class="mb-1.5 block text-sm font-medium text-slate-600">Content</label>
                        <textarea id="content" name="content" rows="6" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('content') }}</textarea>
                    </div>

                    @include('admin.announcements._image-field', [
                        'inputId' => 'announcement-image',
                        'imagePath' => null,
                    ])

                    <label class="inline-flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-2.5 text-sm text-slate-600">
                        <input type="hidden" name="is_published" value="0">
                        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', true)) class="h-4 w-4 rounded border-0 bg-slate-200 text-indigo-400 focus:ring-2 focus:ring-indigo-200">
                        Publish immediately
                    </label>

                    <div>
                        <label for="expires_at" class="mb-1.5 block text-sm font-medium text-slate-600">Expires At</label>
                        <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}"
                            class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <p class="mt-1.5 text-xs text-slate-400">Optional. After this date and time, the announcement is hidden from public and mobile views.</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                        Save Announcement
                    </button>
                    <a href="{{ route('admin.announcements.index') }}" class="inline-flex items-center justify-center rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    @include('admin.announcements._image-preview-script')
@endsection