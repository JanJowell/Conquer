@extends('admin.layouts.app')

@section('title', 'Feedback Insights')

@section('content')
<div class="space-y-6">
    @php
        $canModerateFeedback = auth()->user()->hasAdminRole([
            \App\Models\User::ROLE_SUPER_ADMIN,
            \App\Models\User::ROLE_CONTENT_MODERATOR,
        ]);
    @endphp

    <div>
        <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Community Signals</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Feedback Insights</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6d7685]">Review participant feedback patterns, flagged items, and moderation signals using the feedback data currently stored in the platform.</p>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Total Feedback</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($feedbackInsights['total_feedback']) }}</p>
            <p class="mt-2 text-sm text-[#4f5968]">Community posts recorded in the platform.</p>
        </article>

        <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Flagged Items</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($feedbackInsights['flagged_feedback']) }}</p>
            <p class="mt-2 text-sm text-[#4f5968]">Posts currently marked for moderator review.</p>
            @if($canModerateFeedback)
                <a href="{{ route('admin.content.community-posts', ['status' => 'flagged']) }}" class="mt-4 inline-flex items-center text-sm font-semibold text-[#315fa8] transition hover:text-[#244c8a]">
                    Review flagged posts
                    <i class="fas fa-arrow-right ml-2 text-xs"></i>
                </a>
            @endif
        </article>

        <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Suggestions</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($feedbackInsights['suggestions']) }}</p>
            <p class="mt-2 text-sm text-[#4f5968]">Posts that include improvement-oriented keywords.</p>
        </article>

        <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Ratings</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ $feedbackInsights['ratings_available'] ? 'Live' : 'N/A' }}</p>
            <p class="mt-2 text-sm text-[#4f5968]">Average ratings are unavailable until rating fields are added.</p>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(300px,0.9fr)]">
        <article class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="border-b border-[#eef1f4] px-6 py-5">
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Recent Feedback</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Participant</th>
                            <th class="px-6 py-4">Event</th>
                            <th class="px-6 py-4">Content</th>
                            <th class="px-6 py-4">Status</th>
                            @if($canModerateFeedback)
                                <th class="px-6 py-4 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($recentFeedback as $feedback)
                            <tr class="align-top">
                                <td class="px-6 py-5 font-semibold text-[#151b26]">{{ $feedback->user?->name ?? 'Participant' }}</td>
                                <td class="px-6 py-5">{{ $feedback->event?->title ?? 'General Feedback' }}</td>
                                <td class="px-6 py-5 text-[#4f5968]">{{ \Illuminate\Support\Str::limit($feedback->content, 90) }}</td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $feedback->is_flagged ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                        {{ $feedback->is_flagged ? 'Flagged' : 'Visible' }}
                                    </span>
                                </td>
                                @if($canModerateFeedback)
                                    <td class="px-6 py-5 text-right">
                                        <a href="{{ route('admin.content.community-posts.show', $feedback) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                            Open
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canModerateFeedback ? 5 : 4 }}" class="px-6 py-12 text-center text-sm text-[#6d7685]">No feedback records available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $recentFeedback->links() }}
            </div>
        </article>

        <div class="space-y-4">
            <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Insight Summary</h2>
                <div class="mt-5 space-y-4 text-sm text-[#4f5968]">
                    <div class="rounded-xl border border-[#eef1f4] bg-[#fafbfc] px-4 py-4">
                        <p class="font-semibold text-[#151b26]">Complaint Signals</p>
                        <p class="mt-1">{{ number_format($feedbackInsights['complaints']) }} posts contain issue-oriented language such as delays, confusion, or complaints.</p>
                    </div>
                    <div class="rounded-xl border border-[#eef1f4] bg-[#fafbfc] px-4 py-4">
                        <p class="font-semibold text-[#151b26]">Improvement Suggestions</p>
                        <p class="mt-1">{{ number_format($feedbackInsights['suggestions']) }} posts appear to contain actionable suggestions or requests for improvement.</p>
                    </div>
                    <div class="rounded-xl border border-[#eef1f4] bg-[#fafbfc] px-4 py-4">
                        <p class="font-semibold text-[#151b26]">Positive Mentions</p>
                        <p class="mt-1">{{ number_format($feedbackInsights['positive_mentions']) }} posts contain positive signals such as smooth, good, or excellent.</p>
                    </div>
                    <div class="rounded-xl border border-[#eef1f4] bg-[#fafbfc] px-4 py-4">
                        <p class="font-semibold text-[#151b26]">Coverage</p>
                        <p class="mt-1">{{ number_format($feedbackInsights['feedback_events']) }} events currently have linked feedback content in the available dataset.</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Data Note</h2>
                <p class="mt-4 text-sm leading-7 text-[#5a6473]">
                    Your current schema stores text-based community feedback and moderation flags, but it does not yet store structured ratings, attendance, or satisfaction scores. Once those fields are added, this page can expand into full executive-grade feedback analytics.
                </p>
            </article>
        </div>
    </section>
</div>
@endsection
