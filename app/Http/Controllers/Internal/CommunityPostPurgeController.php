<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CommunityPostPurgeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $days = filter_var($request->query('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($days === false) {
            return response()->json([
                'message' => 'The retention period must be a whole number greater than zero.',
            ], 422);
        }

        $exitCode = Artisan::call('community-posts:purge-archived', [
            '--days' => $days,
        ]);

        if ($exitCode !== Command::SUCCESS) {
            return response()->json([
                'message' => 'Archived community post cleanup failed.',
            ], 500);
        }

        return response()->json([
            'message' => 'Archived community post cleanup completed.',
        ]);
    }
}
