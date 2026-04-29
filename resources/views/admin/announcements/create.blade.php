@extends('admin.layouts.app')

@section('title', 'Create Announcement')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Communications</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Create Announcement</h1>
            <p class="mt-2 text-sm text-[#6d7685]">Post a public notice for a specific event or share a general operations update.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.announcements.store') }}" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf

            <div class="grid gap-5">
                <div>
                    <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                    <select id="event_id" name="event_id" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        <option value="">General announcement</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>{{ $event->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="title" class="mb-2 block text-sm font-medium text-[#3d4757]">Title</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div>
                    <label for="content" class="mb-2 block text-sm font-medium text-[#3d4757]">Content</label>
                    <textarea id="content" name="content" rows="6" class="w-full rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none">{{ old('content') }}</textarea>
                </div>

                <label class="inline-flex items-center gap-3 rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#202733]">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', true)) class="h-4 w-4 rounded border-[#cfd5de] text-[#151b26] focus:ring-[#151b26]">
                    Publish immediately
                </label>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                    Save Announcement
                </button>
                <a href="{{ route('admin.announcements.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
