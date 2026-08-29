<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
    <h1>Announcements</h1>

    @forelse($announcements as $announcement)
        <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
            @if ($announcement->image_path)
                <img src="{{ asset('storage/'.$announcement->image_path) }}" alt="{{ $announcement->title }}" style="display: block; width: 100%; max-height: 420px; margin-bottom: 15px; border-radius: 8px; object-fit: contain; background: #f1f5f9;">
            @endif
            <h3>{{ $announcement->title }}</h3>
            <p>{!! nl2br(e($announcement->content)) !!}</p>
            <small>
                Published:
                {{ $announcement->published_at ? $announcement->published_at->format('F d, Y h:i A') : 'N/A' }}
            </small>
        </div>
    @empty
        <p>No announcements available.</p>
    @endforelse
</body>
</html>
