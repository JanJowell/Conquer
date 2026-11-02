<?php

use App\Services\PhilippineLocations;

test('bundled PSA reference has complete checked ancestry and the published national counts', function () {
    config(['venues.locations_path' => resource_path('data/philippines/locations.json')]);
    $locations = app(PhilippineLocations::class);
    $catalog = $locations->catalog();
    expect($catalog['version'])->toBe('2026-09-30')
        ->and(count(array_filter($catalog['areas'], fn ($row) => $row['kind'] === 'province')))->toBe(82)
        ->and(count($catalog['cities']))->toBe(1642)
        ->and($catalog['barangay_count'])->toBe(42010);
    $barangayCount = 0;
    foreach ($catalog['cities'] as $city) {
        if (! isset($catalog['areas'][$city['area_code']])) {
            throw new RuntimeException('City has no valid province/area in bundled reference.');
        }
        $rows = $locations->barangays($city['code']);
        $barangayCount += count($rows);
        foreach ($rows as $barangay) {
            if ($barangay['city_code'] !== $city['code']) {
                throw new RuntimeException('Barangay has no valid city in bundled reference.');
            }
        }
    }
    expect($barangayCount)->toBe(42010);
});

test('bundled reference includes all NCR cities and Manila barangays without invented provinces', function () {
    config(['venues.locations_path' => resource_path('data/philippines/locations.json')]);
    $locations = app(PhilippineLocations::class);
    $ncrCities = $locations->options('cities', '1300000000');
    expect(count($ncrCities))->toBe(17)
        ->and(count($locations->options('barangays', '1380600000')))->toBe(897);
    $catalog = $locations->catalog();
    expect($catalog['areas']['1999900000']['kind'])->toBe('area')
        ->and($catalog['cities']['0990101000']['area_code'])->not->toBe('0990100000');
});
