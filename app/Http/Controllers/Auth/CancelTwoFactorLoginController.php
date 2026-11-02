<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelTwoFactorLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // A pending challenge is a guest session, not an authenticated session.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
