<?php

namespace App\Services\FieldHandlers;

interface FieldHandlerInterface
{
    /**
     * Return column name used to store value: value_text, value_number, value_date, or value_json
     */
    public function getStorageColumn(): string;

    /**
     * Validate raw input value
     */
    public function validate(mixed $value, ?array $options = null): bool;

    /**
     * Format raw input for SQL storage
     */
    public function serialize(mixed $value): mixed;

    /**
     * Parse stored database value for JSON response
     */
    public function deserialize(mixed $value): mixed;
}
