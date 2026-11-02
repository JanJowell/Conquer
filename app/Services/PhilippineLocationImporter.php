<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

class PhilippineLocationImporter
{
    public function readWorkbook(string $file): array
    {
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'F'], true);
            }
        });
        $sheets = $reader->listWorksheetInfo($file);
        usort($sheets, fn (array $a, array $b) => $b['totalRows'] <=> $a['totalRows']);
        $reader->setLoadSheetsOnly($sheets[0]['worksheetName']);
        $workbook = $reader->load($file);
        try {
            $sheet = $workbook->getSheet(0);
            $highestRow = $sheet->getHighestDataRow();
            $columns = [];
            $headerRow = 0;
            foreach (range(1, min(60, $highestRow)) as $row) {
                foreach (range('A', 'F') as $column) {
                    $header = strtolower(trim(preg_replace('/\s+/', ' ', (string) $sheet->getCell($column.$row)->getValue())));
                    if (str_contains($header, '10') && (str_contains($header, 'code') || str_contains($header, 'psgc'))) {
                        $columns['code'] = $column;
                    }
                    if (in_array($header, ['name', 'geographic name', 'geographic location'], true)) {
                        $columns['name'] = $column;
                    }
                    if (in_array($header, ['geographic level', 'geolevel', 'geographic_level'], true)) {
                        $columns['level'] = $column;
                    }
                }
                if (count($columns) === 3) {
                    $headerRow = $row;
                    break;
                }
            }
            if (! $headerRow) {
                throw new RuntimeException('Expected the official 10-digit PSGC, name, and geographic-level spreadsheet headers. No output was written.');
            }
            $units = [];
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $rawCode = trim((string) $sheet->getCell($columns['code'].$row)->getValue());
                if (! preg_match('/^\d{8,10}$/', $rawCode)) {
                    continue;
                }
                $code = str_pad($rawCode, 10, '0', STR_PAD_LEFT);
                $name = trim((string) $sheet->getCell($columns['name'].$row)->getValue());
                $level = strtoupper(trim((string) $sheet->getCell($columns['level'].$row)->getValue()));
                // PSA includes display-only grouping rows explicitly marked as not provinces.
                if ($level === '' && preg_match('/\(Not a Province\)/i', $name)) {
                    continue;
                }
                if ($level === '' && $code === '1999900000' && $name === 'Special Geographic Area') {
                    $level = 'AREA';
                }
                if (! in_array($level, ['REG', 'PROV', 'CITY', 'MUN', 'SUBMUN', 'BGY', 'DIST', 'AREA'], true)) {
                    throw new RuntimeException('Unknown geographic level for code '.$code.': '.$level);
                }
                if ($name === '' || isset($units[$code])) {
                    throw new RuntimeException('Missing name or duplicate PSGC code: '.$code);
                }
                $units[$code] = compact('code', 'name', 'level');
            }

            return $units;
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function compile(array $units): array
    {
        $areas = [];
        $cities = [];
        $barangays = [];
        foreach ($units as $code => $unit) {
            $code = (string) $code;
            if ($unit['level'] === 'PROV') {
                $areas[$code] = ['code' => $code, 'name' => $unit['name'], 'kind' => 'province'];
            } elseif ($unit['level'] === 'AREA') {
                $areas[$code] = ['code' => $code, 'name' => $unit['name'], 'kind' => 'area'];
            }
        }
        foreach ($units as $code => $unit) {
            $code = (string) $code;
            if (! in_array($unit['level'], ['CITY', 'MUN'], true)) {
                continue;
            }
            $provinceCode = substr($code, 0, 5).'00000';
            $regionCode = substr($code, 0, 2).'00000000';
            if (($units[$regionCode]['level'] ?? null) !== 'REG') {
                throw new RuntimeException('City has no matching region: '.$code);
            }
            if (isset($areas[$provinceCode])) {
                $areaCode = $provinceCode;
            } elseif ($regionCode === '1300000000') {
                $areaCode = $regionCode;
                $areas[$areaCode] = ['code' => $areaCode, 'name' => 'Metro Manila (NCR)', 'kind' => 'region'];
            } elseif ($unit['level'] === 'CITY') {
                $areaCode = $code;
                $areas[$areaCode] = ['code' => $areaCode, 'name' => $unit['name'].' (independent city)', 'kind' => 'independent_city'];
            } else {
                $areaCode = $regionCode;
                $areas[$areaCode] = ['code' => $areaCode, 'name' => $units[$regionCode]['name'].' (areas without a province)', 'kind' => 'region'];
            }
            $cities[$code] = ['code' => $code, 'name' => $unit['name'], 'area_code' => $areaCode];
        }
        foreach ($units as $code => $unit) {
            $code = (string) $code;
            if ($unit['level'] !== 'BGY') {
                continue;
            }
            $cityCode = substr($code, 0, 7).'000';
            // Manila's sub-municipal districts are rolled up to the city, not invented provinces.
            if (($units[$cityCode]['level'] ?? null) === 'SUBMUN') {
                $cityCode = substr($code, 0, 5).'00000';
            }
            if (! isset($cities[$cityCode])) {
                throw new RuntimeException('Barangay has no matching city/municipality: '.$code);
            }
            $barangays[$code] = ['code' => $code, 'name' => $unit['name'], 'city_code' => $cityCode];
        }

        return compact('areas', 'cities', 'barangays');
    }
}
