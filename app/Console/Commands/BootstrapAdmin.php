<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapAdmin extends Command
{
    protected $signature = 'admin:bootstrap';

    protected $description = 'Create or promote the one-time administrator configured through deployment variables';

    public function handle(): int
    {
        if (! filter_var(env('ADMIN_BOOTSTRAP_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            $this->components->info('Administrator bootstrap is disabled.');

            return self::SUCCESS;
        }

        $attributes = [
            'name' => env('ADMIN_BOOTSTRAP_NAME'),
            'email' => env('ADMIN_BOOTSTRAP_EMAIL'),
            'password' => env('ADMIN_BOOTSTRAP_PASSWORD'),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => [
                'required',
                'string',
                Password::min(16)->mixedCase()->letters()->numbers()->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        $created = DB::transaction(function () use ($validated): bool {
            $user = User::where('email', $validated['email'])->lockForUpdate()->first();

            if ($user) {
                $user->forceFill([
                    'role' => User::ROLE_SUPER_ADMIN,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                return false;
            }

            $user = new User();
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => User::ROLE_SUPER_ADMIN,
                'email_verified_at' => now(),
            ])->save();

            return true;
        });

        $this->components->info($created
            ? 'Administrator account created.'
            : 'Existing account promoted to super administrator.');
        $this->components->warn('Remove all ADMIN_BOOTSTRAP_* variables and redeploy now.');

        return self::SUCCESS;
    }
}
