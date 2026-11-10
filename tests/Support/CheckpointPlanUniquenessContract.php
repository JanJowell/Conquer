<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Shared by isolated SQLite tests and the dedicated loopback MySQL probe.
// Callers must provide a NEW synthetic database/connection, never application data.
function checkpointPlanUniquenessCases(): array
{
    return ['legacy-schema-reproduction', 'upgrade-metadata-and-preservation', 'raw-database-duplicate-rejection',
        'recording-enabled-refusal', 'existing-plan-refusal', 'duplicate-plans-refusal', 'existing-visit-refusal',
        'missing-plans-refusal', 'missing-scans-refusal', 'index-name-conflict-refusal', 'already-indexed-refusal',
        'rollback-refusal'];
}

function checkpointPlanUniqueIndexes(): array
{
    $indexes = array_values(array_map(fn ($index) => $index['columns'],
        array_filter(Schema::getIndexes('checkpoint_scan_plans'), fn ($index) => $index['unique'])));
    sort($indexes);

    return $indexes;
}

function checkpointPlanContractRows(): array
{
    $data = [];
    foreach (['users', 'events', 'categories', 'registrations', 'category_course_checkpoints', 'checkpoint_scan_plans', 'checkpoint_scans'] as $table) {
        if (Schema::hasTable($table)) {
            $data[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
    }

    return $data;
}

function checkpointPlanContractInsert(int $category, string $fingerprint = 'a'): int
{
    return DB::table('checkpoint_scan_plans')->insertGetId(['event_id' => 1, 'category_id' => $category,
        'fingerprint' => str_repeat($fingerprint, 64), 'category_started_at' => '2026-11-11 06:00:00',
        'snapshot' => json_encode(['synthetic' => true], JSON_THROW_ON_ERROR),
        'created_at' => '2026-11-11 06:00:00', 'updated_at' => '2026-11-11 06:00:00']);
}

function runCheckpointPlanUniquenessCase(string $case, Closure $check): void
{
    $check(in_array($case, checkpointPlanUniquenessCases(), true), 'Known contract case required.');
    $check(Schema::getTableListing(Schema::getCurrentSchemaListing(), schemaQualified: false) === [], 'The contract requires an empty synthetic database.');
    config(['checkpoint_scanning.enabled' => false]);
    foreach (['users', 'events', 'categories', 'registrations', 'category_course_checkpoints'] as $table) {
        Schema::create($table, fn (Blueprint $blueprint) => $blueprint->id());
        DB::table($table)->insert(['id' => 1]);
    }
    DB::table('categories')->insert(['id' => 2]);
    $original = require database_path('migrations/2026_11_10_000001_create_checkpoint_scan_history_tables.php');
    $correction = require database_path('migrations/2026_11_11_000001_add_unique_category_to_checkpoint_scan_plans.php');
    $original->up();
    $check(checkpointPlanUniqueIndexes() === [['id']], 'Reproduce the original missing category uniqueness rule.');

    if ($case === 'legacy-schema-reproduction') {
        checkpointPlanContractInsert(1);
        checkpointPlanContractInsert(1, 'b');
        $check(DB::table('checkpoint_scan_plans')->count() === 2, 'Original schema actually permits duplicate category plans.');

        return;
    }

    if (in_array($case, ['existing-plan-refusal', 'duplicate-plans-refusal', 'existing-visit-refusal'], true)) {
        $plan = checkpointPlanContractInsert(1);
        if ($case === 'duplicate-plans-refusal') {
            checkpointPlanContractInsert(1, 'b');
        }
        if ($case === 'existing-visit-refusal') {
            DB::table('checkpoint_scans')->insert(['checkpoint_scan_plan_id' => $plan, 'event_id' => 1, 'category_id' => 1,
                'registration_id' => 1, 'checkpoint_id' => 1, 'scanned_by_user_id' => 1, 'scanned_by_name' => 'Synthetic staff',
                'client_scan_id' => '00000000-0000-4000-8000-000000000001', 'request_fingerprint' => str_repeat('c', 64),
                'participant_name' => 'Synthetic participant', 'bib_number' => 'synthetic', 'checkpoint_label' => 'Synthetic checkpoint',
                'checkpoint_position' => 1, 'scanned_at' => '2026-11-11 06:01:00', 'elapsed_seconds' => 60]);
        }
    }
    if ($case === 'recording-enabled-refusal') {
        config(['checkpoint_scanning.enabled' => true]);
    }
    if ($case === 'missing-plans-refusal') {
        Schema::drop('checkpoint_scans');
        Schema::drop('checkpoint_scan_plans');
    }
    if ($case === 'missing-scans-refusal') {
        Schema::drop('checkpoint_scans');
    }
    if ($case === 'index-name-conflict-refusal') {
        Schema::table('checkpoint_scan_plans', fn (Blueprint $table) => $table->index('category_id', 'checkpoint_scan_plans_category_id_unique'));
    }
    if ($case === 'already-indexed-refusal') {
        $correction->up();
    }

    $before = checkpointPlanContractRows();
    $columns = Schema::hasTable('checkpoint_scan_plans') ? Schema::getColumnListing('checkpoint_scan_plans') : null;
    $indexes = Schema::hasTable('checkpoint_scan_plans') ? Schema::getIndexes('checkpoint_scan_plans') : null;
    $foreign = Schema::hasTable('checkpoint_scan_plans') ? Schema::getForeignKeys('checkpoint_scan_plans') : null;
    $refusals = ['recording-enabled-refusal' => 'Disable checkpoint recording', 'existing-plan-refusal' => 'Checkpoint history exists',
        'duplicate-plans-refusal' => 'Checkpoint history exists', 'existing-visit-refusal' => 'Checkpoint history exists',
        'missing-plans-refusal' => 'Checkpoint history schema is incomplete', 'missing-scans-refusal' => 'Checkpoint history schema is incomplete',
        'index-name-conflict-refusal' => 'index already exists', 'already-indexed-refusal' => 'index already exists'];
    if (isset($refusals[$case])) {
        $caught = null;
        try {
            $correction->up();
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }
        $check($caught !== null && str_contains($caught->getMessage(), $refusals[$case]), 'Unsafe upgrade must stop with the expected reason.');
        $check(checkpointPlanContractRows() === $before, 'Refused upgrade preserves every synthetic row.');
        if ($columns !== null) {
            $check(Schema::getColumnListing('checkpoint_scan_plans') === $columns, 'Refused upgrade preserves columns.');
            $check(Schema::getIndexes('checkpoint_scan_plans') === $indexes, 'Refused upgrade preserves indexes.');
            $check(Schema::getForeignKeys('checkpoint_scan_plans') === $foreign, 'Refused upgrade preserves foreign keys.');
        }

        return;
    }

    $correction->up();
    $check(checkpointPlanContractRows() === $before, 'Successful upgrade does not mutate business/history rows.');
    $check(Schema::getColumnListing('checkpoint_scan_plans') === $columns, 'Successful upgrade preserves columns.');
    $check(Schema::getForeignKeys('checkpoint_scan_plans') === $foreign, 'Successful upgrade preserves retention constraints.');
    $check(checkpointPlanUniqueIndexes() === [['category_id'], ['id']], 'Actual schema contains the category unique index.');
    $check(config('checkpoint_scanning.enabled') === false, 'Upgrade never enables checkpoint recording.');
    if ($case === 'raw-database-duplicate-rejection') {
        checkpointPlanContractInsert(1);
        $caught = null;
        try {
            checkpointPlanContractInsert(1, 'b');
        } catch (QueryException $exception) {
            $caught = $exception;
        }
        $check($caught !== null && (string) $caught->getCode() === '23000', 'Raw duplicate insert must fail at the database constraint.');
        $check(DB::table('checkpoint_scan_plans')->where('category_id', 1)->count() === 1, 'Rejected duplicate leaves the first plan intact.');
        checkpointPlanContractInsert(2, 'b');
        $check(DB::table('checkpoint_scan_plans')->count() === 2, 'Different categories remain allowed.');
    }
    if ($case === 'rollback-refusal') {
        $after = checkpointPlanContractRows();
        $afterIndexes = Schema::getIndexes('checkpoint_scan_plans');
        $caught = null;
        try {
            $correction->down();
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }
        $check($caught !== null && str_contains($caught->getMessage(), 'history invariant'), 'Rollback cannot remove the uniqueness invariant.');
        $check(checkpointPlanContractRows() === $after && Schema::getIndexes('checkpoint_scan_plans') === $afterIndexes, 'Refused rollback changes no data or index.');
    }
}
