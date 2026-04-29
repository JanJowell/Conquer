@extends('admin.layouts.app')

@section('title', 'Announcements')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Communications</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Announcements</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Publish event updates, schedule changes, and race-day instructions.</p>
            </div>

            <a href="{{ route('admin.announcements.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                <i class="fas fa-plus mr-2 text-xs"></i>
                New Announcement
            </a>
        </div>

        <div class="overflow-hidden rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Announcement</th>
                            <th class="px-6 py-4">Event</th>
                            <th class="px-6 py-4">Published</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($announcements as $announcement)
                            <tr class="align-top">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $announcement->title }}</p>
                                    <p class="mt-1 text-xs leading-6 text-[#6d7685]">{{ \Illuminate\Support\Str::limit($announcement->content, 110) }}</p>
                                </td>
                                <td class="px-6 py-5">{{ $announcement->event?->title ?: 'General announcement' }}</td>
                                <td class="px-6 py-5">{{ $announcement->published_at?->format('M d, Y h:i A') ?: 'Not published yet' }}</td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $announcement->is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-[#eef1f4] text-[#4f5a6a]' }}">
                                        {{ $announcement->is_published ? 'Published' : 'Draft' }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200 px-4 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-[#6d7685]">No announcements have been published yet.</td>
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
@endsection
