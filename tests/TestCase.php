<?php

namespace Tests;

abstract class TestCase
{
    protected static int $assertions = 0;

    protected function assertTrue(bool $condition, string $message = 'Failed asserting that condition is true'): void
    {
        self::$assertions++;
        if (!$condition) {
            throw new \AssertionError($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'Failed asserting that condition is false'): void
    {
        self::$assertions++;
        if ($condition) {
            throw new \AssertionError($message);
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;
        if ($expected !== $actual) {
            $msg = $message ?: "Expected: " . var_export($expected, true) . ", Actual: " . var_export($actual, true);
            throw new \AssertionError($msg);
        }
    }

    protected function assertNotNull(mixed $actual, string $message = 'Expected value to not be null'): void
    {
        self::$assertions++;
        if ($actual === null) {
            throw new \AssertionError($message);
        }
    }

    protected function assertNull(mixed $actual, string $message = 'Expected value to be null'): void
    {
        self::$assertions++;
        if ($actual !== null) {
            throw new \AssertionError($message);
        }
    }

    protected function assertCount(int $expectedCount, array $array, string $message = ''): void
    {
        self::$assertions++;
        if (count($array) !== $expectedCount) {
            $msg = $message ?: "Expected count {$expectedCount}, got " . count($array);
            throw new \AssertionError($msg);
        }
    }

    public static function getAssertionCount(): int
    {
        return self::$assertions;
    }
}
