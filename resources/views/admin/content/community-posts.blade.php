@extends('admin.layouts.app')

@section('title', 'Community Posts')

@section('content')
<div class="mb-6">
    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Community Moderation</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Community Posts</h1>
    <p class="mt-2 text-sm text-[#6d7685]">Review participant discussions, flag inappropriate content, and restore deleted posts when needed.</p>
</div>

<div class="mb-6 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm sm:p-5">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div class="min-w-[220px] flex-1">
            <label for="search" class="mb-2 block text-sm font-medium text-[#111827]">Search posts</label>
            <input
                type="text"
                name="search"
                id="search"
                value="{{ request('search') }}"
                placeholder="Search by content or author..."
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
        </div>

        <div class="min-w-[180px]">
            <label for="status" class="mb-2 block text-sm font-medium text-[#111827]">Status</label>
            <select
                name="status"
                id="status"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
                <option value="">All posts</option>
                <option value="flagged" {{ request('status') === 'flagged' ? 'selected' : '' }}>Flagged</option>
                <option value="deleted" {{ request('status') === 'deleted' ? 'selected' : '' }}>Deleted</option>
            </select>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                <i class="fas fa-search mr-2 text-xs"></i>
                Search
            </button>
            <a href="{{ route('admin.content.community-posts') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                Clear
            </a>
        </div>
    </form>
</div>

<div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[#eef1f4]">
            <thead class="bg-[#fbfcfd]">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Post</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Author</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Event</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Posted</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f4] bg-white">
                @forelse ($posts as $post)
                    <tr class="align-top">
                        <td class="px-6 py-4">
                            <div class="max-w-md">
                                <p class="text-sm leading-6 text-[#202733]">{{ \Illuminate\Support\Str::limit($post->content, 140) }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex min-w-[220px] items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full border border-[#d9dee7] bg-[#f3f4f6] text-sm font-semibold text-[#606978]">
                                    {{ strtoupper(substr($post->user?->name ?? 'P', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-[#111827]">{{ $post->user?->name ?? 'Deleted user' }}</p>
                                    <p class="truncate text-xs text-[#7a8392]">{{ $post->user?->email ?? 'No email available' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#202733]">
                            {{ $post->event?->title ?? $post->event?->name ?? 'No event linked' }}
                        </td>
                        <td class="px-6 py-4">
                            @if ($post->trashed())
                                <span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">
                                    Deleted
                                </span>
                            @elseif ($post->is_flagged)
                                <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                    Flagged
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                    Active
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[#6d7685]">
                            {{ $post->created_at->diffForHumans() }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.content.community-posts.show', $post->id) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#d9dee7] text-[#315fa8] transition hover:bg-[#f8f9fb]" title="View post">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>

                                @if (! $post->trashed())
                                    @if ($post->is_flagged)
                                        <form method="POST" action="{{ route('admin.content.community-posts.unflag', $post) }}" onsubmit="return collectModerationNote(this, 'Reason for unflagging this post?')">
                                            @csrf
                                            <input type="hidden" name="moderation_note">
                                            <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-200 text-emerald-600 transition hover:bg-emerald-50" title="Unflag post">
                                                <i class="fas fa-flag text-xs"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.content.community-posts.flag', $post) }}" onsubmit="return collectModerationNote(this, 'Reason for flagging this post?')">
                                            @csrf
                                            <input type="hidden" name="moderation_note">
                                            <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-amber-200 text-amber-600 transition hover:bg-amber-50" title="Flag post">
                                                <i class="fas fa-flag text-xs"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.content.community-posts.delete', $post) }}" onsubmit="return collectModerationNote(this, 'Reason for deleting this post?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="moderation_note">
                                        <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 text-rose-600 transition hover:bg-rose-50" title="Delete post">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.content.community-posts.restore', $post) }}" onsubmit="return collectModerationNote(this, 'Reason for restoring this post?')">
                                        @csrf
                                        <input type="hidden" name="moderation_note">
                                        <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-200 text-emerald-600 transition hover:bg-emerald-50" title="Restore post">
                                            <i class="fas fa-rotate-left text-xs"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-14 text-center">
                            <div class="mx-auto max-w-md rounded-2xl border border-dashed border-[#d9dee7] bg-[#fbfcfd] px-6 py-8">
                                <p class="text-sm font-semibold text-[#202733]">No community posts found</p>
                                <p class="mt-2 text-sm leading-6 text-[#6d7685]">Try adjusting your filters or come back when participants start posting in the community feed.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($posts->hasPages())
        <div class="border-t border-[#eef1f4] px-6 py-4">
            {{ $posts->links() }}
        </div>
    @endif
</div>

<script>
    function collectModerationNote(form, message) {
        const note = window.prompt(message);

        if (note === null) {
            return false;
        }

        const trimmed = note.trim();

        if (!trimmed) {
            window.alert('Please add a short moderation note.');
            return false;
        }

        form.querySelector('input[name="moderation_note"]').value = trimmed;

        return true;
    }
</script>
@endsection
