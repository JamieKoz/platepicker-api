<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\UserService;

class EnsureUserExists
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function handle(Request $request, Closure $next)
    {
        $authId = $request->header('X-User-ID');
        $userData = json_decode($request->header('X-User-Data', '{}'), true);

        if ($authId) {
            // Create or get user using the data from headers
            $this->userService->findOrCreateUser(
                $authId,
                $userData['name'] ?? null,
                $userData['email'] ?? null
            );
        }

        return $next($request);
    }
}
