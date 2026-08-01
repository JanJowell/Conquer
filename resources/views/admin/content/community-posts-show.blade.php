@extends('admin.layouts.app')

@section('title', 'Community Post Details')

@section('content')
@php
    $status = $post->trashed()
        ? ['label' => 'Deleted', 'classes' => 'bg-rose-100 text-rose-800']
        : ($post->is_flagged
            ? ['label' => 'Flagged', 'classes' => 'bg-amber-100 text-amber-800']
            : ['label' => 'Active', 'classes' => 'bg-emerald-100 text-emerald-800']);
@endphp

<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Community Moderation</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Community Post</h1>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $status['classes'] }}">{{ $status['label'] }}</span>
                <span class="inline-flex rounded-full bg-[#eef2ff] px-3 py-1 text-xs font-semibold text-[#315fa8]">{{ number_format($post->likes_count) }} likes</span>
                <span class="inline-flex rounded-full bg-[#f3f4f6] px-3 py-1 text-xs font-semibold text-[#4f5968]">{{ number_format($post->comments_count) }} comments</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.content.community-posts') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                Back
            </a>
            @if (request('next'))
                <a href="{{ request('next') }}" class="inline-flex items-center justify-center rounded-xl bg-[#151b26] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#232b39]">
                    Next
                    <i class="fas fa-arrow-right ml-2 text-xs"></i>
                </a>
            @endif
        </div>
    </div>

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <article class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Post Content</p>
            @if ($post->title)
                <h2 class="mt-3 text-2xl font-semibold tracking-tight text-[#111827]">{{ $post->title }}</h2>
            @endif
            <div class="mt-4 whitespace-pre-line rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4 text-sm leading-7 text-[#202733]">{{ $post->content }}</div>

            @if ($post->image_path || $post->video_path)
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @if ($post->image_path)
                        <a href="{{ asset('storage/'.$post->image_path) }}" target="_blank" class="block overflow-hidden rounded-2xl border border-[#eef1f4] bg-[#f8f9fb]">
                            <img src="{{ asset('storage/'.$post->image_path) }}" alt="Community post image" class="h-72 w-full object-cover">
                        </a>
                    @endif

                    @if ($post->video_path)
                        <video controls class="h-72 w-full rounded-2xl border border-[#eef1f4] bg-black object-contain">
                            <source src="{{ asset('storage/'.$post->video_path) }}">
                        </video>
                    @endif
                </div>
            @endif
        </article>

        <aside class="space-y-4">
            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Author</p>
                <div class="mt-4 flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full border border-[#d9dee7] bg-[#f3f4f6] text-sm font-semibold text-[#606978]">
                        {{ strtoupper(substr($post->user?->name ?? 'P', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-[#111827]">{{ $post->user?->name ?? 'Participant' }}</p>
                        <p class="truncate text-xs text-[#7a8392]">{{ $post->user?->email ?? 'No email available' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Context</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-[#7a8392]">Event</dt>
                        <dd class="mt-1 font-medium text-[#202733]">{{ $post->event?->title ?? 'No event linked' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#7a8392]">Posted</dt>
                        <dd class="mt-1 font-medium text-[#202733]">{{ $post->created_at?->format('F d, Y h:i A') }}</dd>
                    </div>
                    @if ($post->trashed())
                        <div>
                            <dt class="text-[#7a8392]">Deleted</dt>
                            <dd class="mt-1 font-medium text-[#202733]">{{ $post->deleted_at?->format('F d, Y h:i A') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Latest Moderation</p>
                @if ($post->moderated_at)
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-[#7a8392]">Moderator</dt>
                            <dd class="mt-1 font-medium text-[#202733]">{{ $post->moderator?->name ?? 'Admin user' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#7a8392]">When</dt>
                            <dd class="mt-1 font-medium text-[#202733]">{{ $post->moderated_at?->format('F d, Y h:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#7a8392]">Note</dt>
                            <dd class="mt-1 whitespace-pre-line rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-3 text-[#202733]">{{ $post->moderation_note ?: 'No reason provided.' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="mt-4 text-sm leading-6 text-[#6d7685]">No moderation action has been recorded yet.</p>
                @endif
            </div>

            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Moderation Actions</p>

                @if ($post->trashed())
                    <form method="POST" action="{{ route('admin.content.community-posts.restore', $post->id) }}" class="mt-4 space-y-3">
                        @csrf
                        <label for="restore_note" class="block text-sm font-medium text-[#202733]">Restore reason</label>
                        <textarea id="restore_note" name="moderation_note" rows="3" required class="w-full rounded-xl border border-[#d9dee7] px-3 py-2 text-sm text-[#111827] outline-none focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"></textarea>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            Restore Post
                        </button>
                    </form>
                @else
                    @if ($post->is_flagged)
                        <form method="POST" action="{{ route('admin.content.community-posts.unflag', $post) }}" class="mt-4 space-y-3">
                            @csrf
                            <label for="unflag_note" class="block text-sm font-medium text-[#202733]">Unflag reason</label>
                            <textarea id="unflag_note" name="moderation_note" rows="3" required class="w-full rounded-xl border border-[#d9dee7] px-3 py-2 text-sm text-[#111827] outline-none focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"></textarea>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-emerald-200 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                                Unflag Post
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.content.community-posts.flag', $post) }}" class="mt-4 space-y-3">
                            @csrf
                            <label for="flag_note" class="block text-sm font-medium text-[#202733]">Flag reason</label>
                            <textarea id="flag_note" name="moderation_note" rows="3" required class="w-full rounded-xl border border-[#d9dee7] px-3 py-2 text-sm text-[#111827] outline-none focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"></textarea>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-700 transition hover:bg-amber-50">
                                Flag Post
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.content.community-posts.delete', $post) }}" onsubmit="return confirm('Are you sure you want to delete this post?')" class="mt-5 space-y-3 border-t border-[#eef1f4] pt-5">
                        @csrf
                        @method('DELETE')
                        <label for="delete_note" class="block text-sm font-medium text-[#202733]">Delete reason</label>
                        <textarea id="delete_note" name="moderation_note" rows="3" required class="w-full rounded-xl border border-[#d9dee7] px-3 py-2 text-sm text-[#111827] outline-none focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"></textarea>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                            Delete Post
                        </button>
                    </form>
                @endif
            </div>
        </aside>
    </section>

    <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] px-6 py-4">
            <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Comments</h2>
        </div>

        <div class="divide-y divide-[#eef1f4]">
            @forelse ($post->comments->sortBy('created_at') as $comment)
                <div id="comment-{{ $comment->id }}" class="px-6 py-4 {{ $comment->trashed() ? 'bg-rose-50/40' : ($comment->is_flagged ? 'bg-amber-50/40' : '') }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-[#111827]">{{ $comment->user?->name ?? 'Participant' }}</p>
                                @if ($comment->trashed())
                                    <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800">Deleted</span>
                                @elseif ($comment->is_flagged)
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Flagged</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-[#7a8392]">{{ $comment->created_at?->format('M d, Y h:i A') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($comment->trashed())
                                <form method="POST" action="{{ route('admin.content.community-comments.restore', $comment->id) }}" onsubmit="return collectModerationNote(this, 'Reason for restoring this comment?')">
                                    @csrf
                                    <input type="hidden" name="moderation_note">
                                    <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50">Restore</button>
                                </form>
                            @else
                                @if ($comment->is_flagged)
                                    <form method="POST" action="{{ route('admin.content.community-comments.unflag', $comment) }}" onsubmit="return collectModerationNote(this, 'Reason for unflagging this comment?')">
                                        @csrf
                                        <input type="hidden" name="moderation_note">
                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50">Unflag</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.content.community-comments.flag', $comment) }}" onsubmit="return collectModerationNote(this, 'Reason for flagging this comment?')">
                                        @csrf
                                        <input type="hidden" name="moderation_note">
                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-50">Flag</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.content.community-comments.delete', $comment) }}" onsubmit="return collectModerationNote(this, 'Reason for deleting this comment?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="moderation_note">
                                    <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#202733]">{{ $comment->content }}</p>
                    @if ($comment->moderated_at)
                        <p class="mt-2 text-xs text-[#7a8392]">
                            Moderated by {{ $comment->moderator?->name ?? 'Admin user' }} · {{ $comment->moderation_note ?: 'No reason provided.' }}
                        </p>
                    @endif
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-[#6d7685]">No comments on this post.</div>
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
