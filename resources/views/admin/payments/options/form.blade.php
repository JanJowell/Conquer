@extends('admin.layouts.app')
@section('title', $option->exists ? 'Edit Payment Option' : 'Add Payment Option')
@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @include('admin.payments.partials.tabs')
    <div>
        <h1 class="text-3xl font-semibold text-slate-800">{{ $option->exists ? 'Edit Payment Option' : 'Add Payment Option' }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ ($option->exists ? $option->is_shared : auth()->user()->isSuperAdmin()) ? 'This is a shared option available to all Event Managers.' : 'This personal option is visible only to your account.' }} Event copies remain unchanged when you save here.</p>
    </div>
    @if($errors->has('version'))
        <a href="{{ route('admin.payment-options.edit', $option) }}" class="inline-block text-sm font-semibold text-indigo-700 underline">Reload saved version</a>
    @endif
    <form method="POST" action="{{ $option->exists ? route('admin.payment-options.update', $option) : route('admin.payment-options.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
        @csrf
        @if($option->exists)
            @method('PUT')
            <input type="hidden" name="version" value="{{ old('version', $option->version) }}">
        @endif
        @include('admin.payments.options.fields')
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white">Save Payment Option</button>
            <a href="{{ route('admin.payment-options.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
@push('scripts')
    <script src="{{ asset('js/payment-option-fields.js') }}" defer></script>
@endpush
