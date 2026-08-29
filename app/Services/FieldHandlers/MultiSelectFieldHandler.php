<?php

namespace App\Services\FieldHandlers;

class MultiSelectFieldHandler implements FieldHandlerInterface
{
    public function getStorageColumn(): string
    {
        return 'value_json';
    }

    public function validate(mixed $value, ?array $options = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        if (!empty($options)) {
            foreach ($value as $item) {
                if (!in_array((string)$item, $options, true)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function serialize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $arr = is_array($value) ? array_values($value) : [$value];
        return json_encode($arr);
    }

    public function deserialize(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
