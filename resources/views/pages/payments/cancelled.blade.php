<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Cancelled - {{ config('app.name', 'Conquer') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f7f9] text-[#151b26]">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center px-6 py-12">
        <section class="w-full rounded-2xl border border-amber-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-700">Payment Cancelled</p>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight">No payment was completed.</h1>
            <p class="mt-3 text-sm leading-6 text-[#5f6b7a]">
                You can return to the mobile app and try online checkout again, or submit manual payment proof if needed.
            </p>
        </section>
    </main>
</body>
</html>
