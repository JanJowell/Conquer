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

        <form method="POST" action="{{ route('admin.announcements.store') }}" class="rounded-2xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf

            <div class="grid gap-5">
                <div>
                    <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                    <select id="event_id" name="event_id" class="h-12 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
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
                        <p class="mt-2 text-xs text-[#6d7685]">Event managers must attach announcements to one of their assigned events.</p>
                    @endunless
                </div>

                <div>
                    <label for="title" class="mb-2 block text-sm font-medium text-[#3d4757]">Title</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" class="h-12 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                </div>

                <div>
                    <label for="content" class="mb-2 block text-sm font-medium text-[#3d4757]">Content</label>
                    <textarea id="content" name="content" rows="6" class="w-full rounded-xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">{{ old('content') }}</textarea>
                </div>

                <label class="inline-flex items-center gap-3 rounded-xl border border-[#d9dee7] px-4 py-3 text-sm text-[#202733]">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', true)) class="h-4 w-4 rounded border-[#cfd5de] text-[#151b26] focus:ring-[#151b26]">
                    Publish immediately
                </label>

                <div>
                    <label for="expires_at" class="mb-2 block text-sm font-medium text-[#3d4757]">Expires At</label>
                    <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}"
                        class="h-12 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <p class="mt-2 text-xs text-[#6d7685]">Optional. After this date and time, the announcement is hidden from public and mobile views.</p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39] focus:outline-none focus:ring-2 focus:ring-[#151b26]/30">
                    Save Announcement
                </button>
                <a href="{{ route('admin.announcements.index') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa] focus:outline-none focus:ring-2 focus:ring-[#d9dee7]">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
