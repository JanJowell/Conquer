@if(!$notification->sent_at && $notification->deliveryError())
    <div class="my-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700" role="alert">
        <p class="font-semibold">Delivery needs attention</p>
        <p class="mt-1">{{ $notification->deliveryError() }}</p>
        @php($acceptedCount = count(data_get($notification->data, '_firebase_delivery.accepted_token_hashes', [])))
        @if($acceptedCount > 0)
            <p class="mt-1">{{ $acceptedCount }} device(s) already accepted this message. Keep the message and audience unchanged when retrying to avoid resending to those devices.</p>
        @endif
        <p class="mt-1">After correcting the configuration, edit this notification, enable Active, and choose a future schedule. For an immediate retry, clear the schedule, save, then choose Send now.</p>
    </div>
@endif
