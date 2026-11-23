<?php

namespace Tests\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A one-shot, test-only failure at the checkpoint response boundary. */
final class CheckpointResponseFault
{
    public bool $triggered = false;

    public ?array $discardedReceipt = null;

    public function __construct(public readonly string $stage) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->triggered || ! $request->isMethod('POST') || ! $request->is('api/staff/checkpoint-scans')) {
            return $next($request);
        }

        if ($this->stage === 'before') {
            $this->triggered = true;

            return response()->json(['message' => 'Synthetic response unavailable.'], 503);
        }

        $response = $next($request);
        if ($response->getStatusCode() === 201) {
            // The actual controller has returned, but its receipt never reaches
            // the caller. This simulates response loss, not a real socket fault.
            $this->discardedReceipt = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'];
            $this->triggered = true;

            return response()->json(['message' => 'Synthetic response unavailable.'], 503);
        }

        return $response;
    }
}
