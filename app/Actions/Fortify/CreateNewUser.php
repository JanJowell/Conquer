<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Support\ParticipantBirthdate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),

            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'max:20'],
            'birthdate' => ParticipantBirthdate::rules(required: true),
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:20'],
        ], ParticipantBirthdate::messages())->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'role' => 'runner',
            'phone' => $input['phone'] ?? null,
            'gender' => $input['gender'] ?? null,
            'birthdate' => $input['birthdate'] ?? null,
            'address' => $input['address'] ?? null,
            'emergency_contact_name' => $input['emergency_contact_name'] ?? null,
            'emergency_contact_number' => $input['emergency_contact_number'] ?? null,
        ]);
    }
}
