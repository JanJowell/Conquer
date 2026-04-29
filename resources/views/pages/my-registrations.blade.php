<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Registrations</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
    <h1>My Registrations</h1>

    @forelse($registrations as $registration)
        <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
            <h3>{{ $registration->event->title }}</h3>
            <p><strong>Category:</strong> {{ $registration->category->name }}</p>
            <p><strong>Bib Number:</strong> {{ $registration->bib_number }}</p>
            <p><strong>Status:</strong> {{ ucfirst($registration->status) }}</p>
            <p><strong>Registered At:</strong> {{ optional($registration->registered_at)->format('F d, Y h:i A') }}</p>
        </div>
    @empty
        <p>No registrations found.</p>
    @endforelse
</body>
</html>