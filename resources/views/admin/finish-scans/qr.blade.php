<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIB {{ $registration->bib_number }} Finish QR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } body { background: white !important; } }</style>
</head>
<body class="min-h-screen bg-slate-100 p-6 text-slate-900">
    <main class="mx-auto max-w-xl rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Finish Scanner Simulation</p>
        <h1 class="mt-3 text-3xl font-bold">BIB {{ $registration->bib_number }}</h1>
        <p class="mt-2 text-lg font-semibold">{{ $registration->user?->name }}</p>
        <p class="mt-1 text-sm text-slate-600">{{ $registration->event?->title }} · {{ $registration->category?->name }}</p>

        <div class="mx-auto mt-6 inline-flex rounded-3xl border border-slate-200 bg-white p-4">
            <img src="{{ $qrDataUri }}" alt="Finish scanner QR code for BIB {{ $registration->bib_number }}" class="h-80 w-80 max-w-full">
        </div>

        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-left text-sm leading-6 text-amber-900">
            This QR identifies this exact registration and category. A scan is provisional until an administrator publishes the official result.
        </div>

        <div class="no-print mt-6 flex justify-center gap-3">
            <button onclick="window.print()" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Print BIB QR</button>
            <button onclick="window.close()" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold">Close</button>
        </div>
    </main>
</body>
</html>
