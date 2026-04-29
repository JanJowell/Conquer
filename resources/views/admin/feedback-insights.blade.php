@extends('admin.layouts.app')

@section('title', 'Feedback Insights')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-semibold tracking-tight text-[#111827]">Feedback Insights</h1>
        <p class="mt-2 text-sm leading-6 text-[#6d7685]">
            Review participant feedback patterns, flagged items, and moderation signals using the feedback data currently stored in the platform.
        </p>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm text-[#6d7685]">Total Feedback</p>
            <p class="mt-2 text-4xl font-semibold tracking-tight text-[#111827]">{{ number_format($feedbackInsights['total_feedback']) }}</p>
            <p class="mt-3 text-sm text-[#4f5968]">Community posts and feedback entries recorded.</p>
        </article>

        <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm text-[#6d7685]">Flagged Items</p>
            <p class="mt-2 text-4xl font-semibold tracking-tight text-[#111827]">{{ number_format($feedbackInsights['flagged_feedback']) }}</p>
            <p class="mt-3 text-sm text-[#4f5968]">Posts currently marked for moderator review.</p>
        </article>

        <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm text-[#6d7685]">Suggestions</p>
            <p class="mt-2 text-4xl font-semibold tracking-tight text-[#111827]">{{ number_format($feedbackInsights['suggestions']) }}</p>
            <p class="mt-3 text-sm text-[#4f5968]">Posts that include improvement-oriented keywords.</p>
        </article>

        <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm text-[#6d7685]">Ratings</p>
            <p class="mt-2 text-4xl font-semibold tracking-tight text-[#111827]">{{ $feedbackInsights['ratings_available'] ? 'Live' : 'N/A' }}</p>
            <p class="mt-3 text-sm text-[#4f5968]">Average ratings are unavailable until rating fields are added.</p>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(300px,0.9fr)]">
        <article class="rounded-xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="border-b border-[#e9edf2] px-5 py-4">
                <h2 class="text-[1.65rem] font-semibold tracking-tight text-[#111827]">Recent Feedback</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead>
                        <tr class="border-b border-[#e9edf2] text-sm text-[#4f5968]">
                            <th class="px-5 py-4 font-medium">Participant</th>
                            <th class="px-5 py-4 font-medium">Event</th>
                            <th class="px-5 py-4 font-medium">Content</th>
                            <th class="px-5 py-4 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentFeedback as $feedback)
                            <tr class="border-b border-[#eef1f4] text-sm text-[#202733]">
                                <td class="px-5 py-4 font-medium">{{ $feedback->user?->name ?? 'Participant' }}</td>
                                <td class="px-5 py-4">{{ $feedback->event?->title ?? 'General Feedback' }}</td>
                                <td class="px-5 py-4">{{ \Illuminate\Support\Str::limit($feedback->content, 90) }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-lg px-3 py-1 text-xs font-medium {{ $feedback->is_flagged ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $feedback->is_flagged ? 'Flagged' : 'Visible' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-[#6d7685]">No feedback records available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4">
                {{ $recentFeedback->links() }}
            </div>
        </article>

        <div class="space-y-4">
            <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <h2 class="text-[1.4rem] font-semibold tracking-tight text-[#111827]">Insight Summary</h2>
                <div class="mt-5 space-y-4 text-sm text-[#4f5968]">
                    <div class="rounded-xl border border-[#eef1f4] bg-[#f8f9fb] px-4 py-4">
                        <p class="font-semibold text-[#111827]">Complaint Signals</p>
                        <p class="mt-1">{{ number_format($feedbackInsights['complaints']) }} posts contain issue-oriented language such as delays, confusion, or complaints.</p>
                    </div>
                    <div class="rounded-xl border border-[#eef1f4] bg-[#f8f9fb] px-4 py-4">
                        <p class="font-semibold text-[#111827]">Improvement Suggestions</p>
                        <p class="mt-1">{{ number_format($feedbackInsights['suggestions']) }} posts appear to contain actionable suggestions or requests for improvement.</p>
                    </div>
                    <div class="rounded-xl border border-[#eef1f4] bg-[#f8f9fb] px-4 py-4">
                        <p class="font-semibold text-[#111827]">Positive Mentions</p>
                        <p class="mt-1">{{ number_format($feedbackInsights['positive_mentions']) }} posts contain positive signals such as smooth, good, or excellent.</p>
                    </div>
                    <div class="rounded-xl border border-[#eef1f4] bg-[#f8f9fb] px-4 py-4">
                        <p class="font-semibold text-[#111827]">Coverage</p>
                        <p class="mt-1">{{ number_format($feedbackInsights['feedback_events']) }} events currently have linked feedback content in the available dataset.</p>
                    </div>
                </div>
            </article>

            <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <h2 class="text-[1.4rem] font-semibold tracking-tight text-[#111827]">Data Note</h2>
                <p class="mt-4 text-sm leading-7 text-[#5a6473]">
                    Your current schema stores text-based community feedback and moderation flags, but it does not yet store structured ratings, attendance, or satisfaction scores. Once those fields are added, this page can expand into full executive-grade feedback analytics.
                </p>
            </article>
        </div>
    </section>
</div>
@endsection
