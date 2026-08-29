<?php

namespace App\Services\FieldHandlers;

class UrlFieldHandler implements FieldHandlerInterface
{
    public function getStorageColumn(): string
    {
        return 'value_text';
    }

    public function validate(mixed $value, ?array $options = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return filter_var((string)$value, FILTER_VALIDATE_URL) !== false;
    }

    public function serialize(mixed $value): ?string
    {
        return $value !== null ? trim((string)$value) : null;
    }

    public function deserialize(mixed $value): ?string
    {
        return $value !== null ? (string)$value : null;
    }
}
