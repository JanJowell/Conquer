<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\RaceResultResource;
use App\Http\Resources\Api\RegistrationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserActivityController extends Controller
{
    public function registrations(Request $request): JsonResponse
    {
        $registrations = $request->user()->registrations()
            ->with(['event', 'category.event', 'latestPayment', 'raceResult', 'issuedEBadges.badge'])
            ->latest()
            ->get();

        return response()->json([
            'data' => RegistrationResource::collection($registrations),
        ]);
    }

    public function results(Request $request): JsonResponse
    {
        $results = $request->user()->raceResults()
            ->with(['event', 'category'])
            ->latest()
            ->get();

        return response()->json([
            'data' => RaceResultResource::collection($results),
        ]);
    }
}
