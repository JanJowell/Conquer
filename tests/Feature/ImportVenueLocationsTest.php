<?php

use App\Models\Event;
use App\Models\Venue;

test('reference import requires a real file and valid reference date without changing database data', function () {
    $this->artisan('venues:import-locations', ['file' => 'missing.xlsx', '--reference-date' => '2026-09-30'])
        ->expectsOutputToContain('existing official XLSX')->assertFailed();
    $this->artisan('venues:import-locations', ['file' => 'missing.xlsx', '--reference-date' => '2026-99-99'])
        ->expectsOutputToContain('existing official XLSX')->assertFailed();
    expect(Venue::count())->toBe(0)->and(Event::count())->toBe(0);
});

test('reference import never silently overwrites the bundled nationwide reference', function () {
    $reference = resource_path('data/philippines/locations.json');
    $before = hash_file('sha256', $reference);
    $workbook = tempnam(sys_get_temp_dir(), 'venue-import-guard-').'.xlsx';
    // An existing source path is sufficient: overwrite protection runs before reading it.
    touch($workbook);
    try {
        $this->artisan('venues:import-locations', ['file' => $workbook, '--reference-date' => '2026-09-30'])
            ->expectsOutputToContain('Reference file already exists')->assertFailed();
        expect(hash_file('sha256', $reference))->toBe($before);
    } finally {
        unlink($workbook);
        // tempnam created the stem before the .xlsx suffix was added.
        unlink(substr($workbook, 0, -5));
    }
});
