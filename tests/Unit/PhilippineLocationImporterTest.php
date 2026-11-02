<?php

use App\Services\PhilippineLocationImporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function venueReferenceUnits(): array
{
    $rows = [
        ['0400000000', 'Test Region', 'REG'],
        ['0402100000', 'Cavite', 'PROV'],
        ['0402103000', 'Test Component City', 'CITY'],
        ['0402103001', 'Test Barangay', 'BGY'],
        ['1300000000', 'National Capital Region', 'REG'],
        ['1380600000', 'City of Manila', 'CITY'],
        ['1380601000', 'Test Manila District', 'SUBMUN'],
        ['1380601001', 'Barangay 1', 'BGY'],
        ['1100000000', 'Test Independent Region', 'REG'],
        ['1130700000', 'Test Independent City', 'CITY'],
        ['1130700001', 'Test Independent Barangay', 'BGY'],
        ['1900000000', 'Test Special Region', 'REG'],
        ['1999900000', 'Special Geographic Area', 'AREA'],
        ['1999901000', 'Test SGA Municipality', 'MUN'],
        ['1999901001', 'Test SGA Barangay', 'BGY'],
    ];
    $units = [];
    foreach ($rows as [$code, $name, $level]) {
        $units[$code] = compact('code', 'name', 'level');
    }

    return $units;
}

test('reference compiler preserves component cities NCR Manila districts and independent cities', function () {
    $catalog = (new PhilippineLocationImporter)->compile(venueReferenceUnits());
    expect($catalog['cities']['0402103000']['area_code'])->toBe('0402100000')
        ->and($catalog['areas']['1300000000']['kind'])->toBe('region')
        ->and($catalog['cities']['1380600000']['area_code'])->toBe('1300000000')
        ->and($catalog['barangays']['1380601001']['city_code'])->toBe('1380600000')
        ->and($catalog['areas']['1130700000']['kind'])->toBe('independent_city')
        ->and($catalog['cities']['1130700000']['area_code'])->toBe('1130700000')
        ->and($catalog['areas']['1999900000']['kind'])->toBe('area')
        ->and($catalog['cities']['1999901000']['area_code'])->toBe('1999900000');
});

test('reference compiler fails instead of silently omitting orphan barangays', function () {
    $units = venueReferenceUnits();
    unset($units['1380600000']);
    expect(fn () => (new PhilippineLocationImporter)->compile($units))->toThrow(RuntimeException::class, 'Barangay has no matching city');
});

test('official workbook reader keeps leading zero codes and detects the geographic headers', function () {
    $file = tempnam(sys_get_temp_dir(), 'venue-psgc-test-');
    $workbook = new Spreadsheet;
    try {
        $sheet = $workbook->getActiveSheet();
        $sheet->fromArray([['10-digit PSGC Code', 'Name', 'Correspondence Code', 'Geographic Level']]);
        $row = 2;
        foreach (venueReferenceUnits() as $unit) {
            $sheet->fromArray([[$unit['code'], $unit['name'], '', $unit['level'] === 'AREA' ? '' : $unit['level']]], null, 'A'.$row++);
        }
        $sheet->fromArray([['0990100000', 'City of Isabela (Not a Province)', '', '']], null, 'A'.$row);
        (new Xlsx($workbook))->save($file);
        $units = (new PhilippineLocationImporter)->readWorkbook($file);
        expect($units['0402103000']['code'])->toBe('0402103000')
            ->and($units['1380601000']['level'])->toBe('SUBMUN')
            ->and(count($units))->toBe(count(venueReferenceUnits()));
    } finally {
        $workbook->disconnectWorksheets();
        unlink($file);
    }
});
