<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\BannedIP;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(function (Request $request) {
            if (BannedIP::isActiveFor($request->ip())) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'This IP address has been blocked.',
                ]);
            }

            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return null;
            }

            if ($user->isBanned()) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'This account has been banned. Please contact the super administrator for assistance.',
                ]);
            }

            if (! $user->isAdmin()) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Web access is limited to admin accounts. Participants should use the mobile application.',
                ]);
            }

            return $user;
        });

        Fortify::loginView(function () {
            return view('pages.auth.login');
        });

        Fortify::registerView(function () {
            return view('pages.auth.register');
        });

        Fortify::requestPasswordResetLinkView(function () {
            return view('pages.auth.forgot-password');
        });

        Fortify::resetPasswordView(function (Request $request) {
            return view('pages.auth.reset-password', [
                'request' => $request,
            ]);
        });

        Fortify::verifyEmailView(function () {
            return view('pages.auth.verify-email');
        });

        Fortify::confirmPasswordView(function () {
            return view('pages.auth.confirm-password');
        });

        Fortify::twoFactorChallengeView(function () {
            return view('pages.auth.two-factor-challenge');
        });

        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;

            return Limit::perMinute(5)->by(Str::transliterate(Str::lower($email).'|'.$request->ip()));
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            $identifier = (string) ($request->input('email')
                ?? $request->input('username')
                ?? $request->input('identifier'));

            return Limit::perMinute(5)->by(Str::transliterate(Str::lower($identifier)).'|'.$request->ip());
        });

        RateLimiter::for('mobile-registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('mobile-verification', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('mobile-api', fn (Request $request) => Limit::perMinute(120)->by(
            $request->user()?->getAuthIdentifier() ?: $request->ip()
        ));

        RateLimiter::for('payment-webhook', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
