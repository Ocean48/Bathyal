<?php

namespace App\Services\FieldHandlers;

class TextFieldHandler implements FieldHandlerInterface
{
    public function getStorageColumn(): string
    {
        return 'value_text';
    }

    public function validate(mixed $value, ?array $options = null): bool
    {
        return is_scalar($value) || $value === null;
    }

    public function serialize(mixed $value): ?string
    {
        return $value !== null ? (string)$value : null;
    }

    public function deserialize(mixed $value): ?string
    {
        return $value !== null ? (string)$value : null;
    }
}
