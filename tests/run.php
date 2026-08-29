<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

// Autoload test classes
spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'Tests\\')) {
        $rel = substr($class, 6);
        $file = __DIR__ . '/' . str_replace('\\', '/', $rel) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

echo "========================================\n";
echo " Running Bathyal PHP Test Suite\n";
echo "========================================\n\n";

$testFiles = [
    __DIR__ . '/Unit/PermEngineTest.php',
    __DIR__ . '/Unit/GroupEngineTest.php',
    __DIR__ . '/Unit/AutoSchedulerTest.php',
    __DIR__ . '/Unit/CustomFieldEngineTest.php',
    __DIR__ . '/Unit/RecurrenceEngineTest.php',
    __DIR__ . '/Integration/ApiIntegrationTest.php',
    __DIR__ . '/Integration/SystemResetTest.php',
];

$passCount = 0;
$failCount = 0;

foreach ($testFiles as $file) {
    require_once $file;
    $className = 'Tests\\' . str_replace('/', '\\', substr(str_replace(__DIR__ . '/', '', $file), 0, -4));
    
    if (class_exists($className)) {
        $ref = new ReflectionClass($className);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
        $instance = new $className();

        foreach ($methods as $method) {
            if (str_starts_with($method->getName(), 'test')) {
                try {
                    $instance->{$method->getName()}();
                    echo " [PASS] {$className}::{$method->getName()}\n";
                    $passCount++;
                } catch (Throwable $e) {
                    echo " [FAIL] {$className}::{$method->getName()}\n";
                    echo "        Error: {$e->getMessage()}\n";
                    $failCount++;
                }
            }
        }
    }
}

echo "\n----------------------------------------\n";
echo " Assertions: " . Tests\TestCase::getAssertionCount() . "\n";
echo " Passed:     {$passCount}\n";
echo " Failed:     {$failCount}\n";
echo "----------------------------------------\n";

if ($failCount > 0) {
    exit(1);
}

echo " All PHP Unit Tests Passed Successfully!\n";
exit(0);
