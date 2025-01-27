<?php

namespace App\Services;

use App\Models\User;

class UserService
{
    public function findOrCreateUser(string $authId, string $name = null, string $email = null): User
    {
        return User::firstOrCreate(
             ['auth_id' => $authId],
            [
                'name' => $name ?? $email ?? 'User',
                'email' => $email ?? $authId . '@example.com',
                'password' => null
            ]
        );
    }
}
