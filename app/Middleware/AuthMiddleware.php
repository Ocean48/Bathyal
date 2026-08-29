<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\SessionManager;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        $user = null;

        // 1. Check Bearer Token
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            $userId = SessionManager::validateToken($bearerToken);
            if ($userId) {
                $user = Database::fetchOne(
                    "SELECT id, email, full_name, default_mode, created_at FROM users WHERE id = :id",
                    ['id' => $userId]
                );
            }
        }

        // 2. Check Session Cookie
        if (!$user && SessionManager::check()) {
            $sessionUser = SessionManager::user();
            if ($sessionUser && isset($sessionUser['id'])) {
                $user = Database::fetchOne(
                    "SELECT id, email, full_name, default_mode, created_at FROM users WHERE id = :id",
                    ['id' => $sessionUser['id']]
                );
            }
        }

        if (!$user) {
            Response::error('Unauthorized. Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $request->setUser($user);

        return $next($request);
    }
}
