<?php

namespace App\Services\FieldHandlers;

class UserFieldHandler implements FieldHandlerInterface
{
    public function getStorageColumn(): string
    {
        return 'value_number';
    }

    public function validate(mixed $value, ?array $options = null): bool
    {
        return is_numeric($value) || $value === null;
    }

    public function serialize(mixed $value): ?int
    {
        return ($value !== null && $value !== '') ? (int)$value : null;
    }

    public function deserialize(mixed $value): ?int
    {
        return $value !== null ? (int)$value : null;
    }
}
