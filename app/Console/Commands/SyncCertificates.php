<?php

namespace App\Console\Commands;

use App\Services\CertificateIssuer;
use Illuminate\Console\Command;

class SyncCertificates extends Command
{
    protected $signature = 'certificates:sync {--event= : Only synchronize one event ID} {--notify : Notify newly eligible participants}';

    protected $description = 'Issue or synchronize E-Certificates for eligible completed registrations';

    public function handle(CertificateIssuer $issuer): int
    {
        $eventId = $this->option('event');
        $count = $eventId
            ? $issuer->syncForEvent((int) $eventId, (bool) $this->option('notify'))
            : $issuer->syncAll((bool) $this->option('notify'));

        $this->info("Synchronized {$count} certificate record(s).");

        return self::SUCCESS;
    }
}
