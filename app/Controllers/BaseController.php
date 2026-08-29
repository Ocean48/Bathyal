<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

abstract class BaseController
{
    protected function json(mixed $data = null, int $statusCode = Response::HTTP_OK, array $meta = []): void
    {
        Response::json($data, $statusCode, $meta);
    }

    protected function error(string $message, int $statusCode = Response::HTTP_BAD_REQUEST, mixed $details = null): void
    {
        Response::error($message, $statusCode, $details);
    }

    protected function getUser(Request $request): ?array
    {
        return $request->user();
    }

    protected function getUserId(Request $request): int
    {
        $user = $this->getUser($request);
        if (!$user || !isset($user['id'])) {
            $this->error('Unauthorized access', Response::HTTP_UNAUTHORIZED);
        }
        return (int)$user['id'];
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = "The {$field} field is required.";
                } elseif ($rule === 'email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email address.";
                } elseif (str_starts_with($rule, 'min:') && $value !== null) {
                    $min = (int)substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                } elseif (str_starts_with($rule, 'in:') && $value !== null) {
                    $options = explode(',', substr($rule, 3));
                    if (!in_array((string)$value, $options, true)) {
                        $errors[$field][] = "The {$field} must be one of: " . implode(', ', $options);
                    }
                }
            }

            if (!isset($errors[$field]) && array_key_exists($field, $data)) {
                $validated[$field] = $value;
            }
        }

        if (!empty($errors)) {
            $this->error('Validation failed', Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
        }

        return $validated;
    }
}
