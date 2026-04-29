<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'We could not find an account with that email address.',
            ])->withInput();
        }

        if (! $user->isAdmin()) {
            return back()->withErrors([
                'email' => 'Password recovery on this page is limited to admin accounts.',
            ])->withInput();
        }

        $broker = Password::broker(config('fortify.passwords'));
        $token = $broker->createToken($user);

        try {
            $user->sendPasswordResetNotification($token);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'email' => 'We could not send the password reset email right now. Please check your mail settings and try again.',
            ])->withInput();
        }

        $response = back()->with('status', 'We have emailed your password reset link.');

        if (app()->environment('local') && config('mail.default') === 'log') {
            $response->with('reset_url', route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]));
        }

        return $response;
    }
}
