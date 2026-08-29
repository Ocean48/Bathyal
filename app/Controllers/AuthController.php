<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\SessionManager;

class AuthController extends BaseController
{
    public function login(Request $request): void
    {
        $payload = $request->getJson();
        $this->validate($payload, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = trim(strtolower($payload['email']));
        $password = $payload['password'];

        $user = Database::fetchOne(
            "SELECT * FROM users WHERE email = :email LIMIT 1",
            ['email' => $email]
        );

        if (!$user || !SessionManager::verifyPassword($password, $user['password_hash'])) {
            $this->error('Invalid email or password credentials', Response::HTTP_UNAUTHORIZED);
        }

        $token = SessionManager::login($user);

        // Fetch user workspaces
        $workspaces = Database::fetchAll(
            "SELECT w.id, w.name, w.slug, w.is_personal, wm.role
             FROM workspaces w
             JOIN workspace_members wm ON w.id = wm.workspace_id
             WHERE wm.user_id = :user_id
             ORDER BY w.is_personal DESC, w.created_at ASC",
            ['user_id' => $user['id']]
        );

        $this->json([
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'default_mode' => $user['default_mode'],
            ],
            'token' => $token,
            'workspaces' => $workspaces,
        ]);
    }

    public function register(Request $request): void
    {
        $payload = $request->getJson();
        $this->validate($payload, [
            'email' => 'required|email',
            'password' => 'required|min:6',
            'full_name' => 'required|min:2',
        ]);

        $email = trim(strtolower($payload['email']));
        $existing = Database::fetchOne("SELECT id FROM users WHERE email = :email LIMIT 1", ['email' => $email]);
        if ($existing) {
            $this->error('An account with this email already exists', Response::HTTP_BAD_REQUEST);
        }

        $passwordHash = SessionManager::hashPassword($payload['password']);
        $fullName = trim($payload['full_name']);
        $mode = $payload['default_mode'] ?? 'simple';

        $userId = Database::transaction(function () use ($email, $passwordHash, $fullName, $mode) {
            $uId = Database::insertGetId(
                "INSERT INTO users (email, password_hash, full_name, default_mode, created_at)
                 VALUES (:email, :pwd, :name, :mode, NOW())",
                [
                    'email' => $email,
                    'pwd' => $passwordHash,
                    'name' => $fullName,
                    'mode' => $mode,
                ]
            );

            // Create default personal workspace
            $slug = 'personal-' . $uId;
            $wsId = Database::insertGetId(
                "INSERT INTO workspaces (name, slug, is_personal, created_at)
                 VALUES (:name, :slug, 1, NOW())",
                [
                    'name' => 'My Personal Tasks',
                    'slug' => $slug,
                ]
            );

            // Add as owner
            Database::execute(
                "INSERT INTO workspace_members (workspace_id, user_id, role)
                 VALUES (:ws_id, :user_id, 'owner')",
                [
                    'ws_id' => $wsId,
                    'user_id' => $uId,
                ]
            );

            return $uId;
        });

        $user = Database::fetchOne("SELECT id, email, full_name, default_mode FROM users WHERE id = :id", ['id' => $userId]);
        $token = SessionManager::login($user);

        $this->json([
            'user' => $user,
            'token' => $token,
        ], Response::HTTP_CREATED);
    }

    public function logout(Request $request): void
    {
        SessionManager::logout();
        $this->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): void
    {
        $user = $this->getUser($request);
        if (!$user) {
            $this->error('Not authenticated', Response::HTTP_UNAUTHORIZED);
        }

        $workspaces = Database::fetchAll(
            "SELECT w.id, w.name, w.slug, w.is_personal, wm.role
             FROM workspaces w
             JOIN workspace_members wm ON w.id = wm.workspace_id
             WHERE wm.user_id = :user_id
             ORDER BY w.is_personal DESC, w.created_at ASC",
            ['user_id' => $user['id']]
        );

        $this->json([
            'user' => $user,
            'workspaces' => $workspaces,
        ]);
    }

    public function updatePreferences(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();
        $this->validate($payload, [
            'default_mode' => 'required|in:simple,enterprise',
        ]);

        Database::execute(
            "UPDATE users SET default_mode = :mode WHERE id = :id",
            [
                'mode' => $payload['default_mode'],
                'id' => $userId,
            ]
        );

        $this->json([
            'message' => 'Preferences updated successfully',
            'default_mode' => $payload['default_mode'],
        ]);
    }
}
