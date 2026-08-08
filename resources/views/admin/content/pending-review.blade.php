@extends('admin.layouts.app')

@section('title', 'Pending Review')

@section('content')
@php
    $totalPending = $flaggedPosts->count()
        + $deletedPosts->count()
        + $flaggedComments->count()
        + $deletedComments->count()
        + $trainingDrafts->count();
    $flaggedPostItems = $flaggedPosts->values();
    $deletedPostItems = $deletedPosts->values();
    $flaggedPostLinks = [];
    $deletedPostLinks = [];

    for ($index = $flaggedPostItems->count() - 1; $index >= 0; $index--) {
        $flaggedPostLinks[$index] = route('admin.content.community-posts.show', [
            'post' => $flaggedPostItems[$index],
            'next' => $flaggedPostLinks[$index + 1] ?? null,
        ]);
    }

    for ($index = $deletedPostItems->count() - 1; $index >= 0; $index--) {
        $deletedPostLinks[$index] = route('admin.content.community-posts.show', [
            'post' => $deletedPostItems[$index]->id,
            'next' => $deletedPostLinks[$index + 1] ?? null,
        ]);
    }
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Moderation Queue</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Pending Review</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Review reported posts, moderated comments, deleted items, and draft training content.</p>
    </div>

    <div class="rounded-2xl border border-[#d9dee7] bg-white px-5 py-3 text-sm font-semibold text-[#202733]">
        {{ number_format($totalPending) }} items
    </div>
</div>

<div class="grid gap-4 md:grid-cols-5">
    <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
        <p class="text-sm text-[#6d7685]">Reported Posts</p>
        <p class="mt-2 text-3xl font-semibold text-[#111827]">{{ number_format($flaggedPosts->count()) }}</p>
    </div>
    <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
        <p class="text-sm text-[#6d7685]">Deleted Posts</p>
        <p class="mt-2 text-3xl font-semibold text-[#111827]">{{ number_format($deletedPosts->count()) }}</p>
    </div>
    <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
        <p class="text-sm text-[#6d7685]">Flagged Comments</p>
        <p class="mt-2 text-3xl font-semibold text-[#111827]">{{ number_format($flaggedComments->count()) }}</p>
    </div>
    <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
        <p class="text-sm text-[#6d7685]">Deleted Comments</p>
        <p class="mt-2 text-3xl font-semibold text-[#111827]">{{ number_format($deletedComments->count()) }}</p>
    </div>
    <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
        <p class="text-sm text-[#6d7685]">Training Drafts</p>
        <p class="mt-2 text-3xl font-semibold text-[#111827]">{{ number_format($trainingDrafts->count()) }}</p>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] px-6 py-4">
            <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Reported Posts</h2>
        </div>
        <div class="divide-y divide-[#eef1f4]">
            @forelse ($flaggedPosts as $post)
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-[#111827]">{{ $post->user?->name ?? 'Deleted user' }}</p>
                            <p class="mt-2 text-sm leading-6 text-[#4f5968]">{{ \Illuminate\Support\Str::limit($post->content ?: 'Media-only post', 180) }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 font-semibold text-amber-800">{{ $post->pending_reports_count }} verified {{ \Illuminate\Support\Str::plural('report', $post->pending_reports_count) }}</span>
                                <span class="rounded-full px-2.5 py-1 font-semibold {{ $post->is_flagged ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">{{ $post->is_flagged ? 'Temporarily hidden' : 'Still visible' }}</span>
                            </div>
                            <p class="mt-2 text-xs text-[#7a8392]">{{ $post->event?->title ?? 'No event linked' }} · {{ $post->latest_reported_at ? \Illuminate\Support\Carbon::parse($post->latest_reported_at)->diffForHumans() : $post->created_at?->diffForHumans() }}</p>
                        </div>
                        <a href="{{ $flaggedPostLinks[$loop->index] }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] px-4 py-2 text-sm font-semibold text-[#315fa8] transition hover:bg-[#f8f9fb]">Open</a>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-[#6d7685]">No reported posts awaiting review.</div>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] px-6 py-4">
            <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Flagged Comments</h2>
        </div>
        <div class="divide-y divide-[#eef1f4]">
            @forelse ($flaggedComments as $comment)
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-[#111827]">{{ $comment->user?->name ?? 'Deleted user' }}</p>
                            <p class="mt-2 text-sm leading-6 text-[#4f5968]">{{ \Illuminate\Support\Str::limit($comment->content, 180) }}</p>
                            <p class="mt-2 text-xs text-[#7a8392]">{{ $comment->post?->event?->title ?? 'No event linked' }} · {{ $comment->moderated_at?->diffForHumans() ?? $comment->created_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($comment->post)
                                <a href="{{ route('admin.content.community-posts.show', $comment->post) }}#comment-{{ $comment->id }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] px-4 py-2 text-sm font-semibold text-[#315fa8] transition hover:bg-[#f8f9fb]">Open</a>
                            @endif
                            <form method="POST" action="{{ route('admin.content.community-comments.unflag', $comment) }}" onsubmit="return collectModerationNote(this, 'Reason for unflagging this comment?')">
                                @csrf
                                <input type="hidden" name="moderation_note">
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">Unflag</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-[#6d7685]">No flagged comments.</div>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] px-6 py-4">
            <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Deleted Posts</h2>
        </div>
        <div class="divide-y divide-[#eef1f4]">
            @forelse ($deletedPosts as $post)
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-[#111827]">{{ $post->user?->name ?? 'Deleted user' }}</p>
                            <p class="mt-2 text-sm leading-6 text-[#4f5968]">{{ \Illuminate\Support\Str::limit($post->content ?: 'Media-only post', 180) }}</p>
                            <p class="mt-2 text-xs text-[#7a8392]">Deleted {{ $post->deleted_at?->diffForHumans() }}</p>
                        </div>
                        <a href="{{ $deletedPostLinks[$loop->index] }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] px-4 py-2 text-sm font-semibold text-[#315fa8] transition hover:bg-[#f8f9fb]">Open</a>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-[#6d7685]">No deleted posts awaiting review.</div>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] px-6 py-4">
            <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Deleted Comments</h2>
        </div>
        <div class="divide-y divide-[#eef1f4]">
            @forelse ($deletedComments as $comment)
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-[#111827]">{{ $comment->user?->name ?? 'Deleted user' }}</p>
                            <p class="mt-2 text-sm leading-6 text-[#4f5968]">{{ \Illuminate\Support\Str::limit($comment->content, 180) }}</p>
                            <p class="mt-2 text-xs text-[#7a8392]">Deleted {{ $comment->deleted_at?->diffForHumans() }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.content.community-comments.restore', $comment->id) }}" onsubmit="return collectModerationNote(this, 'Reason for restoring this comment?')">
                            @csrf
                            <input type="hidden" name="moderation_note">
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">Restore</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-[#6d7685]">No deleted comments awaiting review.</div>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm xl:col-span-2">
        <div class="border-b border-[#eef1f4] px-6 py-4">
            <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Training Drafts</h2>
        </div>
        <div class="divide-y divide-[#eef1f4]">
            @forelse ($trainingDrafts as $module)
                <div class="flex flex-wrap items-start justify-between gap-4 p-6">
                    <div>
                        <p class="text-sm font-semibold text-[#111827]">{{ $module->title }}</p>
                        <p class="mt-2 text-sm leading-6 text-[#4f5968]">{{ \Illuminate\Support\Str::limit($module->description, 180) }}</p>
                        <p class="mt-2 text-xs text-[#7a8392]">Updated {{ $module->updated_at?->diffForHumans() }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.content.training-modules.show', $module) }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] px-4 py-2 text-sm font-semibold text-[#315fa8] transition hover:bg-[#f8f9fb]">Preview</a>
                        <a href="{{ route('admin.content.training-modules.edit', $module) }}" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1f2937]">Edit</a>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-[#6d7685]">No training drafts awaiting review.</div>
            @endforelse
        </div>
    </section>
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
