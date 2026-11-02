<div class="mt-4 space-y-4" data-revision-comparison>
    @if ($historicalComparison ?? false)
        <p class="text-sm text-slate-500">Saved submission only. Current and Proposed are the values recorded when submitted, not the manager's ongoing edits. For a first submission, all supplied details are listed as Added.</p>
    @else
        <p class="text-sm text-slate-500">Only differences are listed. Current is what participants see now; Proposed is this revision. Reviewing this list does not publish changes.</p>
    @endif
    @if (! $revisionComparison['available'])
        <p role="alert" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">The revision comparison is unavailable. Do not approve until its review record has been checked.</p>
    @else
        @if ($revisionComparison['applied'])
            <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">This revision has already been published. This compares its saved setup with the current public setup, not a historical before-and-after record.</p>
        @elseif ($revisionComparison['stale'])
            <p role="alert" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">The public setup changed after this revision started. Approval will be blocked; the manager must start a fresh revision. Current values below reflect the public setup now.</p>
        @endif
        @forelse ($revisionComparison['sections'] as $section)
            <section class="min-w-0 rounded-xl border border-slate-200 p-3">
                <h3 class="mb-3 flex flex-wrap items-center gap-2 font-semibold text-slate-700">
                    {{ $section['title'] }}
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ $section['change'] }}</span>
                </h3>
                @if ($section['change'] === 'Removed')
                    <p class="mb-3 text-sm text-slate-500">On approval, removed categories are closed and payment options are disabled to preserve history. Checkpoints are removed. Existing safety checks still apply.</p>
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] table-fixed text-left text-sm">
                        <caption class="sr-only">{{ $section['title'] }} changes</caption>
                        <thead class="bg-slate-50 text-slate-600">
                            <tr><th scope="col" class="w-1/3 p-3">Field</th><th scope="col" class="w-1/3 p-3">Current</th><th scope="col" class="w-1/3 p-3">Proposed</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($section['rows'] as $row)
                                <tr class="align-top">
                                    <th scope="row" class="break-words p-3 font-medium text-slate-700">{{ $row['field'] }}<span class="mt-1 block text-xs font-normal text-slate-500">{{ $row['change'] }}</span></th>
                                    @foreach (['current', 'proposed'] as $side)
                                        <td class="break-words p-3 text-slate-600">
                                            <span class="whitespace-pre-line">{{ $row[$side]['text'] }}</span>
                                            @if ($row[$side]['details'] !== null)
                                                <details class="mt-2">
                                                    <summary class="cursor-pointer text-indigo-600">View route coordinates</summary>
                                                    <div class="mt-2 max-h-64 overflow-auto whitespace-pre-line break-words rounded-lg bg-slate-50 p-2 text-xs">{{ $row[$side]['details'] }}</div>
                                                </details>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">No setup changes found.</p>
        @endforelse
    @endif
</div>
