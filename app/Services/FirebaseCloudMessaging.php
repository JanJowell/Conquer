<?php

namespace App\Services;

use App\Models\PushNotification;
use App\Models\UserDeviceToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirebaseCloudMessaging
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function sendNotification(PushNotification $notification, Collection $users): array
    {
        $userIds = $users->pluck('id')->filter()->unique()->values();

        if ($userIds->isEmpty()) {
            return $this->result('No target users matched this notification.', configured: $this->isConfigured(), processed: true);
        }

        $deviceTokens = UserDeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->pluck('token');

        if ($deviceTokens->isEmpty()) {
            return $this->result('No saved device tokens found for the target users.', configured: $this->isConfigured(), processed: true);
        }

        if (! $this->isConfigured()) {
            return $this->result('Firebase Cloud Messaging is not configured.', configured: false, attempted: $deviceTokens->count(), retry: false);
        }

        $sent = 0;
        $failed = 0;
        $retryableFailures = 0;

        foreach ($deviceTokens->unique()->values() as $token) {
            $tokenResult = $this->sendToToken($notification, $token);

            if ($tokenResult['sent']) {
                $sent++;
            } else {
                $failed++;

                if ($tokenResult['retry']) {
                    $retryableFailures++;
                }
            }
        }

        return [
            'configured' => true,
            'attempted' => $sent + $failed,
            'sent' => $sent,
            'failed' => $failed,
            'processed' => $sent > 0 || $retryableFailures === 0,
            'retry' => $retryableFailures > 0,
            'message' => $sent > 0
                ? "Notification delivered to {$sent} device token(s)."
                : ($retryableFailures > 0
                    ? 'Firebase delivery failed temporarily. The scheduled notification will be retried.'
                    : 'Firebase was configured, but no device token accepted the notification.'),
        ];
    }

    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        return filled(config('services.firebase.project_id'))
            && filled($credentials['client_email'] ?? null)
            && filled($credentials['private_key'] ?? null);
    }

    private function sendToToken(PushNotification $notification, string $token): array
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->post($this->sendUrl(), [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $notification->title,
                            'body' => $notification->message,
                        ],
                        'data' => [
                            'notification_id' => (string) $notification->id,
                            'type' => (string) $notification->type,
                            'target_audience' => (string) $notification->target_audience,
                            'target_user_id' => (string) $notification->target_user_id,
                        ] + $this->stringData($notification->data ?? []),
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('FCM send request failed.', [
                'push_notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'retry' => true];
        }

        if ($response->successful()) {
            return ['sent' => true, 'retry' => false];
        }

        $body = $response->json();
        $errorCode = data_get($body, 'error.details.0.errorCode') ?? data_get($body, 'error.status');

        $invalidTokenCodes = ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND', 'SENDER_ID_MISMATCH'];
        $retryableCodes = ['UNAVAILABLE', 'INTERNAL', 'RESOURCE_EXHAUSTED', 'DEADLINE_EXCEEDED'];

        if (in_array($errorCode, $invalidTokenCodes, true)) {
            UserDeviceToken::where('token', $token)->delete();
        }

        Log::warning('FCM rejected a device token.', [
            'push_notification_id' => $notification->id,
            'status' => $response->status(),
            'error_code' => $errorCode,
            'body' => $body,
        ]);

        return [
            'sent' => false,
            'retry' => in_array($errorCode, $retryableCodes, true),
        ];
    }

    private function stringData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) || $value === null ? (string) $value : json_encode($value)])
            ->all();
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase_fcm_access_token', now()->addMinutes(50), function () {
            $credentials = $this->credentials();
            $now = time();

            $jwt = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
                .'.'.$this->base64UrlEncode(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => self::TOKEN_URL,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));

            openssl_sign($jwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            $assertion = $jwt.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Unable to obtain Firebase access token.');
            }

            return $response->json('access_token');
        });
    }

    private function credentials(): array
    {
        $path = config('services.firebase.credentials');

        if ($path) {
            $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path)
                ? $path
                : base_path($path);

            if (is_file($resolvedPath)) {
                return $this->decodeCredentials(file_get_contents($resolvedPath));
            }
        }

        if ($json = config('services.firebase.credentials_json')) {
            return $this->decodeCredentials($json);
        }

        if ($base64 = config('services.firebase.credentials_base64')) {
            $decoded = base64_decode((string) $base64, true);

            if ($decoded !== false) {
                return $this->decodeCredentials($decoded);
            }
        }

        return [
            'client_email' => config('services.firebase.client_email'),
            'private_key' => str_replace('\n', "\n", (string) config('services.firebase.private_key')),
        ];
    }

    private function decodeCredentials(string $credentials): array
    {
        return json_decode($credentials, true) ?: [];
    }

    private function sendUrl(): string
    {
        return 'https://fcm.googleapis.com/v1/projects/'.config('services.firebase.project_id').'/messages:send';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function result(string $message, bool $configured, int $attempted = 0, bool $processed = false, bool $retry = false): array
    {
        return [
            'configured' => $configured,
            'attempted' => $attempted,
            'sent' => 0,
            'failed' => 0,
            'processed' => $processed,
            'retry' => $retry,
            'message' => $message,
        ];
    }
}
