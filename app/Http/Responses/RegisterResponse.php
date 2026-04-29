<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        auth()->logout();

        return redirect()->route('login')->with('success', 'Account created successfully. Please log in.');
    }
}