<?php

namespace App\Services\FieldHandlers;

class NumberFieldHandler implements FieldHandlerInterface
{
    public function getStorageColumn(): string
    {
        return 'value_number';
    }

    public function validate(mixed $value, ?array $options = null): bool
    {
        return is_numeric($value) || $value === null;
    }

    public function serialize(mixed $value): ?float
    {
        return ($value !== null && $value !== '') ? (float)$value : null;
    }

    public function deserialize(mixed $value): ?float
    {
        return $value !== null ? (float)$value : null;
    }
}
