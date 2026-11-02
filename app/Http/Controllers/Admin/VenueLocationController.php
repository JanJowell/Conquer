<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PhilippineLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VenueLocationController extends Controller
{
    public function __invoke(Request $request, PhilippineLocations $locations): JsonResponse
    {
        $input = $request->validate([
            'level' => ['required', Rule::in(['areas', 'cities', 'barangays'])],
            'parent' => [Rule::requiredIf($request->input('level') !== 'areas'), 'nullable', 'string', 'regex:/^\d{10}$/'],
        ]);

        return response()->json(['data' => $locations->options($input['level'], $input['parent'] ?? null)])
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
