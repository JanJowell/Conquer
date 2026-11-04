@if ($errors->any())
    <div class="mb-5 min-w-0 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 [overflow-wrap:anywhere]" role="alert" tabindex="-1" data-event-errors>
        <p class="font-semibold">Please correct the following fields.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $message)
                    <li><a href="#event-setup-form" class="underline underline-offset-2" data-event-error-field="{{ $field }}">{{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
        <p class="mt-3 text-xs">Your text and selections are kept. For security, choose any new image files again before retrying.</p>
    </div>
@endif
