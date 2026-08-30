@extends('admin.layouts.app')

@section('title', 'Feedback Insights')

@section('content')
<div class="space-y-6">
    @php
        $canModerateCommunity = auth()->user()->hasAdminRole([
            \App\Models\User::ROLE_SUPER_ADMIN,
            \App\Models\User::ROLE_CONTENT_MODERATOR,
        ]);
        $rating = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2).' / 5';
    @endphp

    <div>
        <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Participant Experience</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Feedback Insights</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-[#6d7685]">Review verified post-event ratings submitted for completed participant registrations. Feedback is linked to the participant for accountability and is not anonymous.</p>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Completed Feedback</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($feedbackInsights['structured_feedback_count']) }}</p>
            <p class="mt-2 text-sm text-[#4f5968]">One response per completed category registration.</p>
        </article>
        <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Overall Rating</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ $rating($feedbackInsights['average_overall_rating']) }}</p>
            <p class="mt-2 text-sm text-[#4f5968]">Average required participant rating.</p>
        </article>
        <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Events Covered</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($feedbackInsights['structured_feedback_events']) }}</p>
            <p class="mt-2 text-sm text-[#4f5968]">Events with at least one verified response.</p>
        </article>
        <article class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Written Comments</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($feedbackInsights['structured_comments_count']) }}</p>
            <p class="mt-2 text-sm text-[#4f5968]">Optional comments accompanying ratings.</p>
        </article>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            'Organization' => $feedbackInsights['average_organization_rating'],
            'Route' => $feedbackInsights['average_route_rating'],
            'Safety' => $feedbackInsights['average_safety_rating'],
            'Experience' => $feedbackInsights['average_experience_rating'],
        ] as $label => $value)
            <article class="rounded-2xl border border-[#d9dee7] bg-white px-5 py-4 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">{{ $label }}</p>
                <p class="mt-2 text-xl font-semibold text-[#151b26]">{{ $rating($value) }}</p>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] px-6 py-5">
            <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Verified Post-event Feedback</h2>
            <p class="mt-1 text-sm text-[#6d7685]">Participants may update a response for seven days after first submitting it. Administrators can review but cannot alter participant feedback.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#eef1f4]">
                <thead class="bg-[#fafbfc]">
                    <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                        <th class="px-6 py-4">Participant</th>
                        <th class="px-6 py-4">Event / Category</th>
                        <th class="px-6 py-4">Overall</th>
                        <th class="px-6 py-4">Detailed Ratings</th>
                        <th class="px-6 py-4">Comment</th>
                        <th class="px-6 py-4">Submitted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                    @forelse ($recentEventFeedback as $feedback)
                        <tr class="align-top">
                            <td class="px-6 py-5">
                                <p class="font-semibold text-[#151b26]">{{ $feedback->user?->name ?? 'Deleted participant' }}</p>
                                <p class="mt-1 text-xs text-[#6d7685]">Registration #{{ $feedback->registration_id }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <p class="font-medium text-[#151b26]">{{ $feedback->event?->title ?? 'Deleted event' }}</p>
                                <p class="mt-1 text-xs text-[#6d7685]">{{ $feedback->category?->name ?? 'Deleted category' }}</p>
                            </td>
                            <td class="px-6 py-5 font-semibold text-amber-600">{{ $feedback->overall_rating }} / 5</td>
                            <td class="px-6 py-5 text-xs leading-6 text-[#4f5968]">
                                <p>Organization: {{ $feedback->organization_rating ?? 'N/A' }}</p>
                                <p>Route: {{ $feedback->route_rating ?? 'N/A' }}</p>
                                <p>Safety: {{ $feedback->safety_rating ?? 'N/A' }}</p>
                                <p>Experience: {{ $feedback->experience_rating ?? 'N/A' }}</p>
                            </td>
                            <td class="max-w-sm px-6 py-5 text-[#4f5968]">{{ $feedback->comment ?: 'No written comment.' }}</td>
                            <td class="whitespace-nowrap px-6 py-5">{{ $feedback->submitted_at?->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-[#6d7685]">No verified post-event feedback has been submitted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-[#eef1f4] px-6 py-4">{{ $recentEventFeedback->withQueryString()->links() }}</div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#eef1f4] px-6 py-5">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Community Signals</h2>
                <p class="mt-1 text-sm text-[#6d7685]">Community posts remain informal discussion and are separate from verified post-event ratings.</p>
            </div>
            @if($canModerateCommunity)
                <a href="{{ route('admin.content.community-posts', ['status' => 'flagged']) }}" class="text-sm font-semibold text-[#315fa8] hover:text-[#244c8a]">Review flagged posts</a>
            @endif
        </div>
        <div class="grid gap-4 border-b border-[#eef1f4] bg-[#fafbfc] p-5 sm:grid-cols-2 xl:grid-cols-4">
            <p class="text-sm text-[#4f5968]"><span class="font-semibold text-[#151b26]">{{ number_format($feedbackInsights['total_feedback']) }}</span> posts</p>
            <p class="text-sm text-[#4f5968]"><span class="font-semibold text-[#151b26]">{{ number_format($feedbackInsights['flagged_feedback']) }}</span> flagged</p>
            <p class="text-sm text-[#4f5968]"><span class="font-semibold text-[#151b26]">{{ number_format($feedbackInsights['suggestions']) }}</span> suggestion signals</p>
            <p class="text-sm text-[#4f5968]"><span class="font-semibold text-[#151b26]">{{ number_format($feedbackInsights['positive_mentions']) }}</span> positive signals</p>
        </div>
        <div class="divide-y divide-[#eef1f4]">
            @forelse ($recentFeedback as $post)
                <article class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="font-semibold text-[#151b26]">{{ $post->user?->name ?? 'Participant' }} · {{ $post->event?->title ?? 'General community post' }}</p>
                        <p class="mt-1 text-sm text-[#4f5968]">{{ \Illuminate\Support\Str::limit($post->content ?: 'Media-only post', 140) }}</p>
                    </div>
                    @if($canModerateCommunity)
                        <a href="{{ route('admin.content.community-posts.show', $post) }}" class="shrink-0 text-sm font-semibold text-[#315fa8] hover:text-[#244c8a]">Open</a>
                    @endif
                </article>
            @empty
                <p class="px-6 py-10 text-center text-sm text-[#6d7685]">No community posts available.</p>
            @endforelse
        </div>
        <div class="border-t border-[#eef1f4] px-6 py-4">{{ $recentFeedback->withQueryString()->links() }}</div>
    </section>
</div>
@endsection
