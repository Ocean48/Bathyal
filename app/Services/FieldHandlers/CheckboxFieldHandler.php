<?php

namespace App\Services\FieldHandlers;

class CheckboxFieldHandler implements FieldHandlerInterface
{
    public function getStorageColumn(): string
    {
        return 'value_number';
    }

    public function validate(mixed $value, ?array $options = null): bool
    {
        return true;
    }

    public function serialize(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    public function deserialize(mixed $value): bool
    {
        return (bool)$value;
    }
}
