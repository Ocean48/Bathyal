<?php

namespace App\Services\FieldHandlers;

class SelectFieldHandler implements FieldHandlerInterface
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
        if (empty($options)) {
            return true;
        }
        return in_array((string)$value, $options, true);
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
