@extends('admin.layouts.app')

@section('title', 'Edit Notification')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Push Notifications</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Edit Notification</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Update the message, audience, timing, and active state.</p>
    </div>

    <form method="POST" action="{{ route('admin.notifications.update', $notification) }}" class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
        @csrf
        @method('PUT')

        <div class="space-y-6 p-6">
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" id="title" required value="{{ old('title', $notification->title) }}"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                <textarea name="message" id="message" rows="5" required
                          class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">{{ old('message', $notification->message) }}</textarea>
                @error('message')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                    <select name="type" id="type" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                        @foreach($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $notification->type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="target_audience" class="block text-sm font-medium text-gray-700">Target Audience</label>
                    <select name="target_audience" id="target_audience" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                        @foreach($audienceOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('target_audience', $notification->target_audience) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('target_audience')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="scheduled_at" class="block text-sm font-medium text-gray-700">Schedule</label>
                    <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                           value="{{ old('scheduled_at', $notification->scheduled_at?->format('Y-m-d\TH:i')) }}"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Leave empty for an immediate notification.</p>
                    @error('scheduled_at')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-3 pt-6">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $notification->is_active))
                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-900">Active</span>
                    </label>
                    <p class="text-sm text-gray-500">Inactive notifications remain saved but are not ready to send.</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-[#eef1f4] bg-gray-50 px-6 py-3">
            <a href="{{ route('admin.notifications.index') }}" class="rounded bg-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-400">
                Cancel
            </a>
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
