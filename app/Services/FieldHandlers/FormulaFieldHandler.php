<?php

namespace App\Services\FieldHandlers;

class FormulaFieldHandler implements FieldHandlerInterface
{
    public function getStorageColumn(): string
    {
        return 'value_text';
    }

    public function validate(mixed $value, ?array $options = null): bool
    {
        return true;
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
