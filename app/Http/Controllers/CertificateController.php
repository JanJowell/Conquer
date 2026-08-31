<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    public function verify(string $token): View
    {
        $certificate = $this->findByToken($token);

        return view('certificates.verify', compact('certificate'));
    }

    public function download(string $token): Response
    {
        $certificate = $this->findByToken($token);
        abort_if($certificate->revoked_at, 410, 'This E-Certificate has been revoked.');

        $verificationUrl = route('certificates.verify', $certificate->verification_token);
        $qrCode = new QrCode(
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 260,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $qrDataUri = (new PngWriter)->write($qrCode)->getDataUri();

        $pdf = Pdf::loadView('certificates.pdf', compact('certificate', 'verificationUrl', 'qrDataUri'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('e-certificate-'.$certificate->certificate_number.'.pdf');
    }

    private function findByToken(string $token): Certificate
    {
        return Certificate::query()
            ->with(['user', 'event', 'category', 'registration.raceResult'])
            ->where('verification_token', $token)
            ->firstOrFail();
    }
}
