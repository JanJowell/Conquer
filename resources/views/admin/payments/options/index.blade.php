@extends('admin.layouts.app')
@section('title', 'Payment Options')
@section('content')
<div class="space-y-6">
    @include('admin.payments.partials.tabs')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-[#151b26]">Payment Options</h1>
            <p class="mt-2 text-sm text-slate-600">Manage payment accounts for your events.</p>
        </div>
        <a href="{{ route('admin.payment-options.create') }}" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white">Add Payment Option</a>
    </div>
    <details data-payment-options-help class="text-sm text-slate-500">
        <summary class="w-fit cursor-pointer rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">How payment options work</summary>
        <div class="mt-2 max-w-3xl space-y-2 leading-relaxed">
            <p>Shared options are available to all Event Managers. Personal options are visible only to their owner.</p>
            <p>Disabling an option prevents new event selections. Editing or disabling it does not change existing event payment details or payment records.</p>
        </div>
    </details>
    @if($errors->any())
        <div role="alert" class="space-y-1 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
            <a href="{{ route('admin.payment-options.index') }}" class="inline-block font-semibold underline">Reload Payment Options</a>
        </div>
    @endif
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($options as $option)
            @php
                $provider = \App\Models\EventPaymentMethod::providers()[$option->provider] ?? $option->provider;
                $heading = $provider.($option->provider !== 'PayMongo' && $option->account_name ? ' — '.$option->account_name : '');
            @endphp
            <section aria-labelledby="payment-option-title-{{ $option->id }}" class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
                <div class="space-y-1">
                    <h2 id="payment-option-title-{{ $option->id }}" class="break-words text-lg font-semibold text-slate-700">{{ $heading }}</h2>
                    @if($option->provider !== 'PayMongo')
                        <p class="break-all text-sm tabular-nums text-slate-600"><span class="sr-only">{{ in_array($option->provider, ['GCash', 'Maya'], true) ? 'Registered mobile number: ' : 'Account number: ' }}</span>{{ $option->account_number ?: ($option->instructions ? 'See instructions for account details.' : 'No account number saved.') }}</p>
                    @else
                        <p class="text-sm text-slate-500">Online checkout</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-indigo-700">{{ $option->is_shared ? 'Shared' : 'Personal' }}</span>
                    <span class="rounded-full px-3 py-1 {{ $option->is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $option->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                </div>
                @if($option->instructions)
                    <details data-payment-instructions class="min-w-0 text-sm text-slate-500">
                        <summary class="w-fit cursor-pointer rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">View instructions<span class="sr-only"> for {{ $heading }}</span></summary>
                        <p class="mt-2 whitespace-pre-line break-words leading-relaxed">{{ $option->instructions }}</p>
                    </details>
                @endif
                @if($option->canManage(auth()->user()))
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('admin.payment-options.edit', $option) }}" class="inline-block rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-indigo-700">Edit details</a>
                        <form method="POST" action="{{ route('admin.payment-options.availability', $option) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="version" value="{{ $option->version }}">
                            <input type="hidden" name="is_enabled" value="{{ $option->is_enabled ? '0' : '1' }}">
                            <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold {{ $option->is_enabled ? 'text-rose-700' : 'text-emerald-700' }}" aria-label="{{ $option->is_enabled ? 'Disable' : 'Enable' }} {{ $heading }}">{{ $option->is_enabled ? 'Disable' : 'Enable' }}</button>
                        </form>
                    </div>
                @else
                    <p class="text-xs text-slate-500">Managed by Admin.</p>
                @endif
            </section>
        @empty
            <p class="rounded-2xl border border-slate-200 bg-white p-6 text-slate-600 md:col-span-2">No saved payment options yet. Add one here before selecting it for a paid event.</p>
        @endforelse
    </div>
    {{ $options->links() }}
</div>
@endsection
