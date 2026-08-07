<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = config('workdesk.bootstrap_admin');

        if (
            blank($credentials['name'] ?? null)
            || blank($credentials['email'] ?? null)
            || blank($credentials['password'] ?? null)
        ) {
            $this->command?->warn(
                'Bootstrap admin dilewati karena environment variable belum lengkap.'
            );

            return;
        }

        Validator::make($credentials, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ])->validate();

        User::query()->firstOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => $credentials['name'],
                'password' => Hash::make($credentials['password']),
                'is_admin' => true,
            ]
        );
    }
}
