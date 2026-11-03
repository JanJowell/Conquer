<dl class="grid min-w-0 gap-x-6 gap-y-4 text-sm md:grid-cols-2">
    @forelse ($rows as $row)
        <div class="min-w-0">
            <dt class="font-medium text-slate-700">{{ $row['field'] }}</dt>
            <dd class="mt-1 min-w-0 break-words whitespace-pre-line text-slate-600">{{ $row['value']['text'] }}</dd>
            @if ($row['value']['details'] !== null)
                <dd class="mt-2 min-w-0">
                    <details>
                        <summary class="cursor-pointer text-indigo-600">View route coordinates</summary>
                        <div class="mt-2 max-h-64 overflow-auto whitespace-pre-line break-words rounded-lg bg-slate-50 p-3 text-xs text-slate-600">{{ $row['value']['details'] }}</div>
                    </details>
                </dd>
            @endif
        </div>
    @empty
        <div><dt class="sr-only">Details</dt><dd class="text-slate-500">No details provided.</dd></div>
    @endforelse
</dl>
