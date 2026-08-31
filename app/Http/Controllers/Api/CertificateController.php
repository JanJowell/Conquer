<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CertificateResource;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $certificates = $request->user()->certificates()
            ->with(['event', 'category'])
            ->latest('issued_at')
            ->get();

        return CertificateResource::collection($certificates);
    }

    public function show(Request $request, Registration $registration): JsonResource
    {
        abort_unless((int) $registration->user_id === (int) $request->user()->id, 403);
        $certificate = $registration->certificate()->with(['event', 'category'])->firstOrFail();

        return new CertificateResource($certificate);
    }
}
