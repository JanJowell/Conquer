<?php

namespace App\Console\Commands;

use App\Services\PhilippineLocationImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportVenueLocations extends Command
{
    protected $signature = 'venues:import-locations {file : Official PSA PSGC XLSX file} {--reference-date= : Reference date, YYYY-MM-DD} {--force : Replace an existing generated reference file}';

    protected $description = 'Build local Philippine venue dropdown data from a downloaded official PSA spreadsheet; no external requests or database writes';

    public function handle(PhilippineLocationImporter $importer): int
    {
        $file = $this->argument('file');
        $version = $this->option('reference-date');
        $output = config('venues.locations_path');
        if (! is_string($version) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $version)
            || ! checkdate((int) substr($version, 5, 2), (int) substr($version, 8, 2), (int) substr($version, 0, 4))
            || ! is_file($file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'xlsx') {
            $this->error('Provide an existing official XLSX file and its --reference-date=YYYY-MM-DD reference date.');

            return self::FAILURE;
        }
        if (is_file($output) && ! $this->option('force')) {
            $this->error('Reference file already exists. Review the new publication before explicitly using --force.');

            return self::FAILURE;
        }
        try {
            $units = $importer->readWorkbook($file);
            $catalog = $importer->compile($units);
            if (count($catalog['areas']) < 80 || count($catalog['cities']) < 1600 || count($catalog['barangays']) < 40000) {
                throw new \RuntimeException('The workbook does not contain a complete nationwide geographic reference.');
            }
            $barangayCount = count($catalog['barangays']);
            $barangayDirectory = 'barangays-'.substr(hash('sha256', json_encode($catalog['barangays'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 16);
            $grouped = [];
            foreach ($catalog['barangays'] as $code => $barangay) {
                $grouped[$barangay['city_code']][$code] = $barangay;
            }
            $chunkPath = dirname($output).'/'.$barangayDirectory;
            File::ensureDirectoryExists($chunkPath);
            foreach ($catalog['cities'] as $cityCode => &$city) {
                $rows = $grouped[$cityCode] ?? [];
                $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
                $city['barangay_count'] = count($rows);
                $city['barangays_sha256'] = hash('sha256', $json);
                File::replace($chunkPath.'/'.$cityCode.'.json', $json);
            }
            unset($city, $catalog['barangays']);
            $catalog = [
                'schema_version' => 2,
                'version' => $version,
                'source' => 'Philippine Statistics Authority, Philippine Standard Geographic Code',
                'source_url' => 'https://psa.gov.ph/classification/psgc/Philippine',
                'source_file' => basename($file),
                'source_sha256' => hash_file('sha256', $file),
                'license' => 'CC BY 4.0; https://psa.gov.ph/terms-of-use',
                'transformations' => 'Province/area grouping for addresses; NCR is an area, independent cities retain their own area; Manila sub-municipal barangays are grouped under City of Manila. Names are retained from the source except descriptive area labels.',
                'barangay_directory' => $barangayDirectory,
                'barangay_count' => $barangayCount,
                ...$catalog,
            ];
            $json = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
            File::ensureDirectoryExists(dirname($output));
            File::replace($output, $json);
        } catch (\Throwable $exception) {
            $this->error('Reference import failed: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Local reference generated: '.count($catalog['areas']).' province/areas, '.count($catalog['cities']).' cities/municipalities, '.$catalog['barangay_count'].' barangays.');
        $this->line('No venue, event, or production database record was modified.');

        return self::SUCCESS;
    }
}
