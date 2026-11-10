<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CheckpointScanException;
use App\Http\Controllers\Controller;
use App\Models\CheckpointScan;
use App\Models\User;
use App\Services\CheckpointScanRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CheckpointScanController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store', 'Vary' => 'Authorization'];

    public function __construct(private readonly CheckpointScanRecorder $recorder) {}

    public function plan(Request $request): JsonResponse
    {
        return $this->handle($request, function () use ($request) {
            $input = $request->validate($this->selectionRules());

            return response()->json(['data' => $this->recorder->plan($request->user(), (int) $input['event_id'], (int) $input['category_id'])], 200, self::HEADERS);
        });
    }

    public function store(Request $request): JsonResponse
    {
        return $this->handle($request, function () use ($request) {
            $input = $request->validate([
                ...$this->selectionRules(), 'scan_mode' => ['required', 'in:checkpoint'],
                'token' => ['required', 'string', 'max:2048'], 'checkpoint_id' => ['required', 'integer', 'min:1'],
                'client_scan_id' => ['required', 'uuid'], 'plan_fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
                'captured_at' => ['missing'], 'captured_offline' => ['missing'], 'scanned_at' => ['missing'],
                'elapsed_seconds' => ['missing'], 'scanned_by_user_id' => ['missing'], 'registration_id' => ['missing'],
                'lap_number' => ['missing'], 'pass_number' => ['missing'],
            ]);
            $result = $this->recorder->record($request->user(), $input);

            return response()->json(['message' => $result['replayed'] ? 'This checkpoint visit was already recorded.' : 'Checkpoint visit recorded. This is not a finish.',
                'idempotent_replay' => $result['replayed'], 'data' => $this->payload($result['scan'])], $result['replayed'] ? 200 : 201, self::HEADERS);
        });
    }

    private function selectionRules(): array
    {
        return ['event_id' => ['required', 'integer', 'min:1'], 'category_id' => ['required', 'integer', 'min:1']];
    }

    private function handle(Request $request, callable $operation): JsonResponse
    {
        $staff = $request->user();
        if (! $staff?->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]) || ! $staff->email_verified_at) {
            return $this->error('This account is not authorized to record checkpoint visits.', 'checkpoint_scanner_forbidden', 403);
        }
        if (! config('checkpoint_scanning.enabled', false)) {
            return $this->error('Checkpoint recording is disabled.', 'checkpoint_recording_disabled', 503);
        }
        foreach (['category_course_checkpoints', 'checkpoint_scan_plans', 'checkpoint_scans'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->error('Checkpoint recording setup is unavailable.', 'checkpoint_recording_unavailable', 503);
            }
        }
        try {
            return $operation();
        } catch (CheckpointScanException $exception) {
            return $this->error($exception->getMessage(), $exception->errorCode, $exception->status);
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'Check the selected checkpoint and online scan request.',
                'code' => 'validation_failed', 'errors' => $exception->errors()], 422, self::HEADERS);
        }
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status, self::HEADERS);
    }

    private function payload(CheckpointScan $scan): array
    {
        $scan->loadMissing('plan');

        return ['id' => $scan->id, 'scan_type' => 'checkpoint', 'recording_mode' => 'online',
            'registration_id' => $scan->registration_id, 'participant_name' => $scan->participant_name,
            'bib_number' => $scan->bib_number, 'event_id' => $scan->event_id, 'category_id' => $scan->category_id,
            'checkpoint_id' => $scan->checkpoint_id, 'checkpoint_label' => $scan->checkpoint_label,
            'checkpoint_position' => $scan->checkpoint_position, 'plan_fingerprint' => $scan->plan->fingerprint,
            'client_scan_id' => $scan->client_scan_id, 'scanned_at' => $scan->scanned_at->toIso8601String(),
            'elapsed_seconds' => $scan->elapsed_seconds, 'after_cutoff' => $scan->after_cutoff,
            'scanned_by' => ['id' => $scan->scanned_by_user_id, 'name' => $scan->scanned_by_name]];
    }
}
