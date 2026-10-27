<?php

namespace App\Services;

use App\Exceptions\CourseMapImageUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleStaticCourseMap
{
    private const HOST = 'https://maps.googleapis.com';

    private const MAX_BYTES = 4000000;

    public function configured(): bool
    {
        $driver = config('cache.stores.'.config('cache.default').'.driver');

        return config('services.google_maps.static_enabled') === true
            && filled(config('services.google_maps.static_key'))
            && $this->secret() !== null
            && (app()->environment('testing') || in_array($driver, ['database', 'redis'], true));
    }

    /** Only accepts the normalized, validated ParticipantCourseMap representation. */
    public function descriptor(?int $categoryId, ?array $map): ?array
    {
        if ($categoryId === null || $map === null || ! $this->configured()) {
            return null;
        }

        try {
            $this->signedUrl($map);
        } catch (CourseMapImageUnavailable) {
            return null;
        }

        return [
            'url' => route('api.categories.course-map-image', [
                'category' => $categoryId,
                'revision' => $this->revision($map),
            ]),
            'requires_authentication' => true,
            'legend' => $this->legend($map),
        ];
    }

    public function revision(array $map): string
    {
        return hash('sha256', 'static-v1:'.json_encode($map, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function legend(array $map): array
    {
        $legend = [[
            ...$map['start'], 'kind' => 'start', 'number' => null,
            'label' => $map['is_loop'] ? 'Start / Finish' : 'Start',
            'marker_label' => 'S', 'marker_color' => 'green',
        ]];

        if (! $map['is_loop']) {
            $legend[] = [
                ...$map['finish'], 'kind' => 'finish', 'number' => null,
                'label' => 'Finish', 'marker_label' => 'F', 'marker_color' => 'red',
            ];
        }

        foreach ($map['checkpoints'] as $index => $checkpoint) {
            // Single-character labels: 35 blue tokens, then 15 purple tokens.
            $legend[] = [
                ...$checkpoint, 'kind' => 'checkpoint',
                'marker_label' => '123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'[$index % 35],
                'marker_color' => $index < 35 ? 'blue' : 'purple',
            ];
        }

        return $legend;
    }

    public function signedUrl(array $map): string
    {
        if (! $this->configured()) {
            throw new CourseMapImageUnavailable('not_configured');
        }

        $params = [
            'size=640x480', 'scale=2', 'format=png', 'maptype=roadmap',
            'path='.rawurlencode('color:0x4F46E5FF|weight:4|enc:'.$this->encodePath($map['path'])),
        ];

        foreach ($this->legend($map) as $marker) {
            $params[] = 'markers='.rawurlencode('color:'.$marker['marker_color'].'|label:'.$marker['marker_label'].'|'
                .$this->coordinate($marker['lat']).','.$this->coordinate($marker['lng']));
        }

        $params[] = 'key='.rawurlencode(config('services.google_maps.static_key'));
        $path = '/maps/api/staticmap?'.implode('&', $params);
        $signature = strtr(base64_encode(hash_hmac('sha1', $path, $this->secret(), true)), '+/', '-_');
        $url = self::HOST.$path.'&signature='.$signature;

        if (strlen($url) > 16384) {
            throw new CourseMapImageUnavailable('route_too_large', 422);
        }

        return $url;
    }

    public function image(array $map): string
    {
        $url = $this->signedUrl($map);
        $this->reserveRequest();

        try {
            // Fixed provider and server-owned parameters only. No redirects or retries.
            $response = Http::connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->withOptions(['stream' => true])->get($url);
            $stream = $response->toPsrResponse()->getBody();

            try {
                if ($response->status() !== 200
                    || strtolower(trim(explode(';', $response->header('Content-Type'))[0])) !== 'image/png'
                    || filled($response->header('X-Staticmap-API-Warning'))
                    || (int) $response->header('Content-Length') > self::MAX_BYTES) {
                    throw new CourseMapImageUnavailable('provider_error', 502);
                }

                $bytes = '';
                while (! $stream->eof() && strlen($bytes) <= self::MAX_BYTES) {
                    $chunk = $stream->read(min(65536, self::MAX_BYTES + 1 - strlen($bytes)));
                    if ($chunk === '') {
                        break;
                    }
                    $bytes .= $chunk;
                }

                $size = strlen($bytes) <= self::MAX_BYTES ? @getimagesizefromstring($bytes) : false;
                if (! $size || $size[0] !== 1280 || $size[1] !== 960 || $size[2] !== IMAGETYPE_PNG) {
                    throw new CourseMapImageUnavailable('invalid_image', 502);
                }

                // A valid PNG header alone does not prove the image is complete.
                $decoded = @imagecreatefromstring($bytes);
                if ($decoded === false) {
                    throw new CourseMapImageUnavailable('invalid_image', 502);
                }
                imagedestroy($decoded);

                return $bytes;
            } finally {
                $stream->close();
            }
        } catch (CourseMapImageUnavailable $exception) {
            throw $exception;
        } catch (Throwable) {
            // Do not report provider exceptions: their messages can contain signed URLs/keys.
            throw new CourseMapImageUnavailable('provider_unavailable', 502);
        }
    }

    private function reserveRequest(): void
    {
        try {
            $reserved = Cache::lock('course-map-image-budget-lock', 5)->block(1, function (): bool {
                $now = now('UTC');
                $key = 'course-map-image-requests:'.$now->format('Y-m-d');
                $used = (int) Cache::get($key, 0);
                $limit = max(0, (int) config('services.google_maps.static_daily_limit', 100));
                if ($used >= $limit) {
                    return false;
                }
                if (! Cache::put($key, $used + 1, $now->addDay()->startOfDay())) {
                    throw new CourseMapImageUnavailable('budget_unavailable');
                }

                return true;
            });
        } catch (Throwable) {
            throw new CourseMapImageUnavailable('budget_unavailable');
        }

        if (! $reserved) {
            throw new CourseMapImageUnavailable('daily_limit', 429);
        }
    }

    private function secret(): ?string
    {
        $value = config('services.google_maps.signing_secret');
        if (! is_string($value) || ! preg_match('/\A[A-Za-z0-9_-]+={0,2}\z/', $value)) {
            return null;
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded !== false && strlen($decoded) >= 16 ? $decoded : null;
    }

    private function coordinate(float $value): string
    {
        return number_format($value, 6, '.', '');
    }

    private function encodePath(array $points): string
    {
        $encoded = '';
        $previous = [0, 0];
        foreach ($points as $point) {
            foreach (['lat', 'lng'] as $index => $axis) {
                $current = (int) round($point[$axis] * 100000);
                $delta = $current - $previous[$index];
                $value = $delta < 0 ? ~($delta << 1) : $delta << 1;
                while ($value >= 0x20) {
                    $encoded .= chr((0x20 | ($value & 0x1F)) + 63);
                    $value >>= 5;
                }
                $encoded .= chr($value + 63);
                $previous[$index] = $current;
            }
        }

        return $encoded;
    }
}
