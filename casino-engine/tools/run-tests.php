<?php

declare(strict_types=1);

/**
 * Zero-dependency test runner.
 *
 * Discovers tests/*Test.php, instantiates each TestCase subclass, runs every
 * public method whose name starts with "test", and reports pass/fail. It
 * honours expectException() the way PHPUnit does (set before the call, checked
 * after). Use it when you don't want to install PHPUnit:
 *
 *     php tools/run-tests.php
 *
 * The CI / repo path is still real PHPUnit via composer; this just mirrors it.
 */

use PHPUnit\Framework\TestCase;

$root = dirname(__DIR__);
require $root . '/autoload.php';
require $root . '/tests/_polyfill/TestCase.php';

// Load test support classes (doubles, helpers) then the test cases.
foreach (glob($root . '/tests/Support/*.php') as $file) {
    require_once $file;
}

$beforeClasses = get_declared_classes();
foreach (glob($root . '/tests/*Test.php') as $file) {
    require_once $file;
}
$testClasses = array_values(array_filter(
    array_diff(get_declared_classes(), $beforeClasses),
    static fn (string $c): bool => is_subclass_of($c, TestCase::class)
));
sort($testClasses);

$totalPass = 0;
$totalFail = 0;
$failures = [];

foreach ($testClasses as $class) {
    $methods = array_filter(
        get_class_methods($class),
        static fn (string $m): bool => str_starts_with($m, 'test')
    );
    sort($methods);

    $short = (new ReflectionClass($class))->getShortName();
    echo "\n\033[1m$short\033[0m\n";

    foreach ($methods as $method) {
        $test = new $class();
        $test->__resetExpectation();
        $thrown = null;
        try {
            $test->$method();
        } catch (Throwable $e) {
            $thrown = $e;
        }
        $expected = $test->__expectedException();

        $ok = false;
        $reason = '';
        if ($expected !== null) {
            if ($thrown !== null && $thrown instanceof $expected) {
                $ok = true;
            } else {
                $reason = $thrown === null
                    ? "expected exception $expected, none thrown"
                    : "expected $expected, got " . get_class($thrown) . ': ' . $thrown->getMessage();
            }
        } else {
            if ($thrown === null) {
                $ok = true;
            } else {
                $reason = get_class($thrown) . ': ' . $thrown->getMessage();
            }
        }

        if ($ok) {
            $totalPass++;
            echo "  \033[32m✓\033[0m $method\n";
        } else {
            $totalFail++;
            $failures[] = "$short::$method — $reason";
            echo "  \033[31m✗\033[0m $method — $reason\n";
        }
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
if ($totalFail === 0) {
    echo "\033[32mOK\033[0m — $totalPass passed.\n";
    exit(0);
}
echo "\033[31mFAILURES\033[0m — $totalPass passed, $totalFail failed:\n";
foreach ($failures as $f) {
    echo "  - $f\n";
}
exit(1);
