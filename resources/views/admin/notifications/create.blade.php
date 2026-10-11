@extends('admin.layouts.app')

@section('title', 'Create Notification')

@section('content')
<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl space-y-6">

        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Notifications</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-700">Create Push Notification</h1>
        </div>

        <div class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <form method="POST" action="{{ route('admin.notifications.store') }}">
                @csrf

                <div class="space-y-5 p-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-slate-600">Title</label>
                        <input type="text" name="title" id="title" required value="{{ old('title') }}"
                               class="mt-1.5 block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        @error('title')
                            <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-slate-600">Message</label>
                        <textarea name="message" id="message" rows="4" required
                                  class="mt-1.5 block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="type" class="block text-sm font-medium text-slate-600">Type</label>
                            <select name="type" id="type" required
                                    class="mt-1.5 block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                @foreach($typeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="target_audience" class="block text-sm font-medium text-slate-600">Target Audience</label>
                            <select name="target_audience" id="target_audience" required
                                    class="mt-1.5 block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                @foreach($audienceOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('target_audience') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('target_audience')
                                <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="scheduled_at" class="block text-sm font-medium text-slate-600">Schedule (Optional)</label>
                            <input type="datetime-local" name="scheduled_at" id="scheduled_at" value="{{ old('scheduled_at') }}"
                                   class="mt-1.5 block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                            <p class="mt-1.5 text-xs text-slate-400">Leave empty to send immediately</p>
                            @error('scheduled_at')
                                <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-2 pt-6">
                            <input type="checkbox" name="is_active" id="is_active" value="1" checked
                                   class="h-4 w-4 rounded border-0 bg-slate-100 text-indigo-400 focus:ring-2 focus:ring-indigo-200">
                            <label for="is_active" class="text-sm text-slate-600">Active</label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 rounded-b-[1.75rem] bg-[#f7f8fc] px-6 py-4">
                    <a href="{{ route('admin.notifications.index') }}"
                       class="rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                        Create Notification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection