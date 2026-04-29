<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Conquer</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
    <h1>Events</h1>

    @forelse($events as $event)
        <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
            <h2>{{ $event->title }}</h2>
            <p><strong>Venue:</strong> {{ $event->venue }}</p>
            <p><strong>Date:</strong> {{ $event->event_date->format('F d, Y') }}</p>
            <p><strong>Status:</strong> {{ ucfirst($event->effective_status) }}</p>
            <p>{{ $event->description }}</p>

            @if($event->categories->count())
                <h4>Categories</h4>
                @foreach($event->categories as $category)
                    <form method="POST" action="{{ route('events.register', [$event, $category]) }}" style="margin-bottom: 10px;">
                        @csrf
                        <strong>{{ $category->name }}</strong> - {{ $category->distance_km }} KM
                        <select name="shirt_size">
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M" selected>M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                        </select>
                        <button type="submit">Register</button>
                    </form>
                @endforeach
            @endif
        </div>
    @empty
        <p>No events found.</p>
    @endforelse
</body>
</html>
