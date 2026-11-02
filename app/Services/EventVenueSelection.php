<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventVenueSelection
{
    public function __construct(private readonly PhilippineLocations $locations) {}

    public function prepare(Request $request, ?Event $event = null): array
    {
        // Preserve compatibility for existing integrations that submit the readable venue string.
        if (! $request->exists('venue_mode')) {
            $legacy = $request->validate(['venue' => ['required', 'string', 'max:255']]);

            return [
                'label' => $legacy['venue'],
                'venue_id' => $event && $event->venue === $legacy['venue'] ? $event->venue_id : null,
            ];
        }

        if (is_string($request->input('venue_mode')) && preg_match('/^saved:([1-9][0-9]*)$/', $request->input('venue_mode'), $match)) {
            $request->merge(['venue_mode' => 'saved', 'venue_id' => $match[1]]);
        }
        $mode = $request->validate(['venue_mode' => ['required', Rule::in(['saved', 'new', 'keep'])]])['venue_mode'];
        if ($mode === 'keep') {
            if (! $event) {
                throw ValidationException::withMessages(['venue_mode' => 'Enter the venue and select its location.']);
            }

            return ['label' => $event->venue, 'venue_id' => $event->venue_id];
        }

        if ($mode === 'saved') {
            $id = $request->validate(['venue_id' => ['required', 'integer', 'min:1']])['venue_id'];
            $venue = Venue::availableTo($request->user())->find($id);
            if (! $venue) {
                throw ValidationException::withMessages(['venue_id' => 'Choose an available saved venue.']);
            }

            return ['label' => $venue->label, 'venue_id' => $venue->id];
        }

        $input = $request->validate([
            'venue_name' => ['required', 'string', 'max:100'],
            // Older forms may still send a separate street address; the compact
            // form includes the meeting point and landmark together in venue_name.
            'venue_address' => ['nullable', 'string', 'max:150'],
            'venue_area_code' => ['required', 'string', 'regex:/^\d{10}$/'],
            'venue_city_code' => ['required', 'string', 'regex:/^\d{10}$/'],
            'venue_barangay_code' => ['required', 'string', 'regex:/^\d{10}$/'],
        ]);
        $address = $this->locations->address($input['venue_area_code'], $input['venue_city_code'], $input['venue_barangay_code']);
        $details = [
            'name' => Str::squish($input['venue_name']),
            'address' => Str::squish($input['venue_address'] ?? ''),
            ...$address,
        ];
        $label = $details['name'].' — '.implode(', ', array_filter([
            $details['address'], $address['barangay_name'], $address['city_name'], $address['area_name'],
        ], fn ($part) => $part !== ''));
        if (Str::length($label) > 255) {
            throw ValidationException::withMessages([
                $details['address'] !== '' ? 'venue_address' : 'venue_name' => 'The full venue address exceeds 255 characters. Shorten the venue or landmark details.',
            ]);
        }

        return ['label' => $label, 'new' => $details];
    }

    public function persist(array $selection, User $user): array
    {
        if (! isset($selection['new'])) {
            return ['venue' => $selection['label'], 'venue_id' => $selection['venue_id']];
        }

        $details = $selection['new'];
        $fingerprint = hash('sha256', Str::lower(json_encode([
            $details['name'], $details['address'], $details['area_code'], $details['city_code'], $details['barangay_code'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));
        $venue = Venue::firstOrCreate(
            ['created_by' => $user->id, 'fingerprint' => $fingerprint],
            [...$details, 'label' => $selection['label']]
        );

        return ['venue' => $venue->label, 'venue_id' => $venue->id];
    }
}
