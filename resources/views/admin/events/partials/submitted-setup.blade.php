<div class="mt-4 min-w-0 space-y-5" data-submitted-setup>
    @foreach ($submittedSetup as $group)
        <section class="min-w-0 space-y-3" aria-label="{{ $group['title'] }}" data-submitted-group="{{ $group['key'] }}">
            <h3 class="flex flex-wrap items-center gap-2 font-semibold text-slate-700">
                {{ $group['title'] }}
                @if ($group['key'] !== 'event')
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ count($group['items']) }}</span>
                @endif
            </h3>
            @forelse ($group['items'] as $item)
                @if ($group['key'] === 'event')
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        @include('admin.events.partials.submitted-setup-values', ['rows' => $item['rows']])
                    </div>
                @else
                    <details class="min-w-0 rounded-xl border border-slate-200 bg-white p-4" data-submitted-item>
                        <summary class="cursor-pointer break-words font-medium text-slate-700">{{ $item['title'] }}</summary>
                        <div class="mt-4">
                            @include('admin.events.partials.submitted-setup-values', ['rows' => $item['rows']])
                        </div>
                    </details>
                @endif
            @empty
                <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">No {{ strtolower($group['title']) }} included.</p>
            @endforelse
        </section>
    @endforeach
</div>
