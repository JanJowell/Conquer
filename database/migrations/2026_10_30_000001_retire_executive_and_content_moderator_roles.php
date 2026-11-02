<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $ids = DB::table('users')->whereIn('role', ['executive', 'content_moderator'])
                ->orderBy('id')->lockForUpdate()->pluck('id')->all();
            if ($ids === []) {
                return;
            }

            // Keep identity, passwords, 2FA secrets, profiles and all owned records.
            // Reauthentication is required; obsolete administrative invitations
            // must not grant access after the account becomes a participant.
            DB::table('users')->whereIn('id', $ids)->update([
                'role' => 'runner',
                'api_token' => null,
                'api_token_expires_at' => null,
                'remember_token' => null,
                'admin_invitation_token' => null,
                'admin_invitation_sent_at' => null,
                'admin_invitation_expires_at' => null,
                'admin_invited_by' => null,
                'two_factor_required' => false,
            ]);
            if (Schema::hasColumn('sessions', 'user_id')) {
                DB::table('sessions')->whereIn('user_id', $ids)->delete();
            }
        });
    }

    public function down(): void
    {
        // Deliberately do not restore privileged roles or revoked credentials.
        // Any future reassignment requires an explicit per-account decision.
    }
};
