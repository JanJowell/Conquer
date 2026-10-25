<?php

namespace App\Services;

use App\Exceptions\FirebaseAuthenticationException;
use App\Models\PushNotification;
use App\Models\UserDeviceToken;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirebaseCloudMessaging
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const AUTH_ERROR = 'Firebase authentication or permissions failed. Delivery is paused. Check the Firebase credentials, project permissions, and platform push credentials, then reactivate this notification to retry.';

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

        $sent = 0;
        $failed = 0;
        $retryableFailures = 0;
        $authRefreshAttempted = false;
        $paused = false;
        $fingerprint = hash('sha256', json_encode([$notification->title, $notification->message, $notification->type,
            $notification->target_audience, $notification->target_user_id, $notification->publicData()]));
        $previous = data_get($notification->data, '_firebase_delivery', []);
        $acceptedHashes = ($previous['fingerprint'] ?? null) === $fingerprint
            ? (array) ($previous['accepted_token_hashes'] ?? []) : [];

        if (! $this->isConfigured()) {
            $message = 'Firebase Cloud Messaging configuration is missing or invalid. Delivery is paused. Correct the configuration, then reactivate this notification to retry.';

            return $this->result($message, configured: false) + [
                'paused' => true,
                'delivery_state' => [
                    'fingerprint' => $fingerprint,
                    'accepted_token_hashes' => $acceptedHashes,
                    'error' => $message,
                    'paused' => true,
                ],
            ];
        }

        foreach ($deviceTokens->unique()->values() as $token) {
            $tokenHash = hash('sha256', $token);
            if (in_array($tokenHash, $acceptedHashes, true)) {
                continue;
            }
            $tokenResult = $this->sendToToken($notification, $token, $authRefreshAttempted);

            if ($tokenResult['sent']) {
                $sent++;
                $acceptedHashes[] = $tokenHash;
            } else {
                $failed++;

                if ($tokenResult['retry']) {
                    $retryableFailures++;
                }
                if ($tokenResult['paused'] ?? false) {
                    $paused = true;
                    break;
                }
            }
        }

        return [
            'configured' => true,
            'attempted' => $sent + $failed,
            'sent' => $sent,
            'failed' => $failed,
            'processed' => ! $paused && $retryableFailures === 0,
            'retry' => ! $paused && $retryableFailures > 0,
            'paused' => $paused,
            'delivery_state' => [
                'fingerprint' => $fingerprint,
                'accepted_token_hashes' => array_values(array_unique($acceptedHashes)),
                'error' => $paused ? self::AUTH_ERROR : null,
                'paused' => $paused,
            ],
            'message' => $paused ? self::AUTH_ERROR : ($retryableFailures > 0
                ? 'Firebase delivery failed temporarily. The scheduled notification will be retried without resending to devices already accepted.'
                : ($sent > 0
                ? "Notification delivered to {$sent} device token(s)."
                : 'Notification processing completed; no additional devices accepted the notification.')),
        ];
    }

    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        return is_string(config('services.firebase.project_id')) && filled(config('services.firebase.project_id'))
            && is_string($credentials['client_email'] ?? null) && filled($credentials['client_email'])
            && is_string($credentials['private_key'] ?? null) && filled($credentials['private_key']);
    }

    private function sendToToken(PushNotification $notification, string $token, bool &$authRefreshAttempted): array
    {
        try {
            $payload = [
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
                    ] + $this->stringData($notification->publicData()),
                ],
            ];
            $response = Http::withToken($this->accessToken())->acceptJson()->timeout(20)->post($this->sendUrl(), $payload);
            // Only a rejected OAuth bearer token is refreshable. APNs/web-push
            // credential failures also use HTTP 401 but require operator action.
            if ($response->status() === 401 && $this->errorCode($response) !== 'THIRD_PARTY_AUTH_ERROR' && ! $authRefreshAttempted) {
                $authRefreshAttempted = true;
                Cache::forget('firebase_fcm_access_token');
                $response = Http::withToken($this->accessToken())->acceptJson()->timeout(20)->post($this->sendUrl(), $payload);
            }
        } catch (FirebaseAuthenticationException $e) {
            Cache::forget('firebase_fcm_access_token');
            Log::warning('Firebase credential authentication failed; delivery paused.', ['push_notification_id' => $notification->id]);

            return ['sent' => false, 'retry' => false, 'paused' => true];
        } catch (\Throwable $e) {
            Log::error('FCM send request failed.', [
                'push_notification_id' => $notification->id,
                'exception_type' => get_class($e),
            ]);

            return ['sent' => false, 'retry' => true];
        }

        if ($response->successful()) {
            return ['sent' => true, 'retry' => false];
        }

        $errorCode = $this->errorCode($response);

        if (in_array($response->status(), [401, 403], true)
            || in_array($errorCode, ['UNAUTHENTICATED', 'PERMISSION_DENIED', 'THIRD_PARTY_AUTH_ERROR', 'SENDER_ID_MISMATCH'], true)) {
            if ($response->status() === 401 && $errorCode !== 'THIRD_PARTY_AUTH_ERROR') {
                Cache::forget('firebase_fcm_access_token');
            }
            Log::warning('Firebase authentication or permissions rejected delivery; paused.', [
                'push_notification_id' => $notification->id, 'status' => $response->status(),
            ]);

            return ['sent' => false, 'retry' => false, 'paused' => true];
        }

        $invalidTokenCodes = ['UNREGISTERED'];
        $retryableCodes = ['UNAVAILABLE', 'INTERNAL', 'RESOURCE_EXHAUSTED', 'DEADLINE_EXCEEDED', 'QUOTA_EXCEEDED'];

        if (in_array($errorCode, $invalidTokenCodes, true)) {
            UserDeviceToken::where('token', $token)->delete();
        }

        Log::warning('FCM rejected a device token.', [
            'push_notification_id' => $notification->id,
            'status' => $response->status(),
            'error_code' => $errorCode,
        ]);

        return [
            'sent' => false,
            'retry' => $response->status() === 429 || $response->serverError() || in_array($errorCode, $retryableCodes, true),
        ];
    }

    private function errorCode(Response $response): ?string
    {
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (is_array($detail) && ($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError') {
                return is_string($detail['errorCode'] ?? null) ? $detail['errorCode'] : null;
            }
        }

        $status = $response->json('error.status');

        return is_string($status) ? $status : null;
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

            try {
                $signed = @openssl_sign($jwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            } catch (\Throwable $e) {
                throw new FirebaseAuthenticationException('Firebase credential signing failed.');
            }
            if (! $signed) {
                throw new FirebaseAuthenticationException('Firebase credential signing failed.');
            }
            $assertion = $jwt.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                if ($response->clientError() && $response->status() !== 429) {
                    throw new FirebaseAuthenticationException('Firebase rejected the service-account credentials.');
                }
                throw new RuntimeException('Unable to obtain Firebase access token.');
            }

            return $token;
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
        $decoded = json_decode($credentials, true);

        return is_array($decoded) ? $decoded : [];
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
