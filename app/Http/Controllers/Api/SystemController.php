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
        $eventInterestTypes = config('conquer.event_interest_types', []);

        return response()->json([
            'app_name' => config('app.name', 'Conquer'),
            'api_version' => 'v1',
            'auth' => [
                'token_type' => 'Bearer',
                'token_ttl_days' => 30,
            ],
            'uploads' => [
                'avatar_max_mb' => 10,
                'avatar_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
                'community_image_max_mb' => 4,
                'community_video_max_mb' => 20,
            ],
            'payments' => [
                'manual_proof_enabled' => true,
                'paymongo_enabled' => filled(config('services.paymongo.secret_key')),
                'paymongo_public_key_configured' => filled(config('services.paymongo.public_key')),
                'paymongo_webhook_configured' => filled(config('services.paymongo.webhook_secret')),
                'paymongo_payment_methods' => config('services.paymongo.payment_methods', []),
            ],
            'interests' => config('conquer.interests', []),
            'event_interest_types' => $eventInterestTypes,
            'training_focus_types' => $eventInterestTypes,
            'shirt_sizes' => config('conquer.shirt_sizes', []),
        ]);
    }
}
