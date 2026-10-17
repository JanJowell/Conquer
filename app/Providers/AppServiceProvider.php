<?php

namespace App\Providers;

use App\Http\Responses\RegisterResponse;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.compact');

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $email = $notifiable->getEmailForPasswordReset();
            $url = route('password.reset', [
                'token' => $token,
                'email' => $email,
            ]);
            $expiresIn = (int) config('auth.passwords.users.expire', 60);

            return (new MailMessage)
                ->subject('Reset your Racetech administrator password')
                ->view([
                    'html' => 'emails.transactional',
                    'text' => 'emails.transactional-text',
                ], [
                    'preheader' => 'Use this secure link to reset your Racetech administrator password.',
                    'heading' => 'Reset your administrator password',
                    'greeting' => 'Hello '.$notifiable->name.',',
                    'intro' => [
                        'We received a request to reset the password for your Racetech administrator account.',
                        'Use the secure button below to choose a new password.',
                    ],
                    'details' => [
                        'Account' => $email,
                        'Link expires' => $expiresIn.' minutes',
                    ],
                    'actionLabel' => 'Reset administrator password',
                    'actionUrl' => $url,
                    'securityNotice' => 'This link is private and time-limited. Do not forward this email or share the link.',
                    'closing' => 'If you did not request a password reset, you can safely ignore this email and keep your current password.',
                ]);
        });

        Mail::extend('brevo', function (array $config): BrevoApiTransport {
            $key = $config['key'] ?? null;

            if (! is_string($key) || trim($key) === '') {
                throw new \InvalidArgumentException('The Brevo mail transport requires a BREVO_API_KEY.');
            }

            $client = HttpClient::create([
                'timeout' => (float) ($config['timeout'] ?? 10),
            ]);

            return new BrevoApiTransport($key, $client);
        });

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
