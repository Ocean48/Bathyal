<?php

namespace App\Services\FieldHandlers;

class DateFieldHandler implements FieldHandlerInterface
{
    public function getStorageColumn(): string
    {
        return 'value_date';
    }

    public function validate(mixed $value, ?array $options = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return strtotime((string)$value) !== false;
    }

    public function serialize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string)$value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    public function deserialize(mixed $value): ?string
    {
        return $value !== null ? (string)$value : null;
    }
}
