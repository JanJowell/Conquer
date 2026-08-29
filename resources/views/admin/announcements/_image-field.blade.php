@php
    $currentImageUrl = $imagePath ? asset('storage/'.$imagePath) : null;
@endphp

<div data-announcement-image-field>
    <label for="{{ $inputId }}" class="mb-2 block text-sm font-semibold text-slate-800">Announcement Image</label>
    <input
        id="{{ $inputId }}"
        name="image"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        data-announcement-image-input
        class="block w-full rounded-xl border border-slate-200 bg-white text-sm text-slate-700 shadow-sm file:mr-4 file:border-0 file:bg-slate-100 file:px-4 file:py-3 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200 focus:outline-none focus:ring-4 focus:ring-sky-100/70"
    >
    <p class="mt-2 text-xs text-slate-500">Optional JPG, PNG, or WebP image up to 5 MB. Content is still required.</p>

    <div class="mt-3 {{ $currentImageUrl ? '' : 'hidden' }}" data-announcement-image-preview-container>
        <img
            src="{{ $currentImageUrl ?: '' }}"
            data-announcement-image-preview
            data-current-src="{{ $currentImageUrl ?: '' }}"
            alt="Announcement image preview"
            class="max-h-72 w-full rounded-xl border border-slate-200 bg-slate-50 object-contain"
        >
    </div>

    @if ($currentImageUrl)
        <label class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-rose-700">
            <input type="hidden" name="remove_image" value="0">
            <input type="checkbox" name="remove_image" value="1" data-announcement-remove-image @checked(old('remove_image')) class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
            Remove current image
        </label>
    @endif

    @if ($showErrors ?? true)
        @error('image') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
    @endif
</div>
