<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name', 'Conquer'),
            'time' => now()->toISOString(),
        ]);
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'app_name' => config('app.name', 'Conquer'),
            'api_version' => 'v1',
            'auth' => [
                'token_type' => 'Bearer',
                'token_ttl_days' => 30,
            ],
            'uploads' => [
                'avatar_max_mb' => 2,
                'avatar_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
                'community_image_max_mb' => 4,
                'community_video_max_mb' => 20,
            ],
            'interests' => ['Running', 'Cycling', 'Duathlon', 'Marathon', 'Trail Run'],
            'shirt_sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', 'Small', 'Medium', 'Large', 'Extra Large'],
        ]);
    }
}
