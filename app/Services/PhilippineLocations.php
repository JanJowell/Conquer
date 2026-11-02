<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class PhilippineLocations
{
    private ?array $catalog = null;

    private ?string $barangayCity = null;

    private array $barangayRows = [];

    public function available(): bool
    {
        return is_file(config('venues.locations_path'));
    }

    public function catalog(): array
    {
        if ($this->catalog === null) {
            if (! $this->available()) {
                throw new ServiceUnavailableHttpException(null, 'The Philippine location list is not installed. Existing venues can still be kept or reused.');
            }
            $this->catalog = json_decode(file_get_contents(config('venues.locations_path')), true, flags: JSON_THROW_ON_ERROR);
        }

        return $this->catalog;
    }

    public function options(string $level, ?string $parent = null): array
    {
        $catalog = $this->catalog();
        $rows = match ($level) {
            'areas' => $catalog['areas'],
            'cities' => array_filter($catalog['cities'], fn (array $row) => $row['area_code'] === $parent),
            'barangays' => $this->barangays($parent ?? ''),
        };

        usort($rows, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        return array_values(array_map(fn (array $row) => ['code' => $row['code'], 'name' => $row['name']], $rows));
    }

    public function address(string $areaCode, string $cityCode, string $barangayCode): array
    {
        $catalog = $this->catalog();
        $area = $catalog['areas'][$areaCode] ?? null;
        $city = $catalog['cities'][$cityCode] ?? null;

        if (! $area) {
            throw ValidationException::withMessages(['venue_area_code' => 'Choose a valid province or area from the list.']);
        }
        if (! $city || $city['area_code'] !== $areaCode) {
            throw ValidationException::withMessages(['venue_city_code' => 'Choose a city/municipality belonging to the selected province or area.']);
        }
        $barangay = $this->barangays($cityCode)[$barangayCode] ?? null;
        if (! $barangay || $barangay['city_code'] !== $cityCode) {
            throw ValidationException::withMessages(['venue_barangay_code' => 'Choose a barangay belonging to the selected city/municipality.']);
        }

        return [
            'area_code' => $areaCode, 'area_name' => $area['name'],
            'city_code' => $cityCode, 'city_name' => $city['name'],
            'barangay_code' => $barangayCode, 'barangay_name' => $barangay['name'],
            'reference_version' => $catalog['version'],
        ];
    }

    public function barangays(string $cityCode): array
    {
        $catalog = $this->catalog();
        $city = $catalog['cities'][$cityCode] ?? null;
        if (! $city || ! preg_match('/^\d{10}$/', $cityCode)) {
            return [];
        }
        if ($this->barangayCity === $cityCode) {
            return $this->barangayRows;
        }
        $directory = $catalog['barangay_directory'] ?? '';
        if (! preg_match('/^barangays-[a-f0-9]{16}$/', $directory)) {
            throw new ServiceUnavailableHttpException(null, 'The local barangay reference needs to be rebuilt.');
        }
        $path = dirname(config('venues.locations_path')).'/'.$directory.'/'.$cityCode.'.json';
        if (! is_file($path) || ! hash_equals($city['barangays_sha256'] ?? '', hash_file('sha256', $path))) {
            throw new ServiceUnavailableHttpException(null, 'The local barangay reference is missing or damaged.');
        }
        $rows = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (count($rows) !== $city['barangay_count']) {
            throw new ServiceUnavailableHttpException(null, 'The local barangay reference is incomplete.');
        }
        $this->barangayCity = $cityCode;
        $this->barangayRows = $rows;

        return $rows;
    }
}
