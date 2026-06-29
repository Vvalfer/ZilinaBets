<?php

declare(strict_types=1);

/**
 * Zero-dependency stand-in for PHPUnit's TestCase, providing just the
 * assertions this suite uses. It is defined ONLY when the real PHPUnit is not
 * loaded, so the very same test files run two ways:
 *
 *   - composer install && ./vendor/bin/phpunit   (real PHPUnit, for the repo/CI)
 *   - php tools/run-tests.php                     (this polyfill, zero install)
 *
 * This is a verification convenience, not a PHPUnit reimplementation.
 */

namespace PHPUnit\Framework {

    if (!class_exists(TestCase::class, false)) {

        class AssertionFailed extends \RuntimeException
        {
        }

        abstract class TestCase
        {
            private ?string $expectedException = null;

            public function __resetExpectation(): void
            {
                $this->expectedException = null;
            }

            public function __expectedException(): ?string
            {
                return $this->expectedException;
            }

            protected function expectException(string $class): void
            {
                $this->expectedException = $class;
            }

            protected static function fail(string $message = ''): void
            {
                throw new AssertionFailed($message !== '' ? $message : 'Failed assertion.');
            }

            protected static function assertTrue($cond, string $m = ''): void
            {
                if ($cond !== true) {
                    self::fail($m ?: 'Failed asserting that value is true.');
                }
            }

            protected static function assertFalse($cond, string $m = ''): void
            {
                if ($cond !== false) {
                    self::fail($m ?: 'Failed asserting that value is false.');
                }
            }

            protected static function assertSame($expected, $actual, string $m = ''): void
            {
                if ($expected !== $actual) {
                    self::fail($m ?: 'Failed asserting two values are identical: '
                        . self::export($expected) . ' !== ' . self::export($actual));
                }
            }

            protected static function assertNotSame($a, $b, string $m = ''): void
            {
                if ($a === $b) {
                    self::fail($m ?: 'Failed asserting two values are not identical.');
                }
            }

            protected static function assertEquals($expected, $actual, string $m = ''): void
            {
                if ($expected != $actual) {
                    self::fail($m ?: 'Failed asserting two values are equal: '
                        . self::export($expected) . ' != ' . self::export($actual));
                }
            }

            protected static function assertNull($v, string $m = ''): void
            {
                if ($v !== null) {
                    self::fail($m ?: 'Failed asserting that value is null.');
                }
            }

            protected static function assertNotNull($v, string $m = ''): void
            {
                if ($v === null) {
                    self::fail($m ?: 'Failed asserting that value is not null.');
                }
            }

            protected static function assertCount(int $expected, $countable, string $m = ''): void
            {
                $actual = count($countable);
                if ($actual !== $expected) {
                    self::fail($m ?: "Failed asserting count $actual matches expected $expected.");
                }
            }

            protected static function assertContains($needle, array $haystack, string $m = ''): void
            {
                if (!in_array($needle, $haystack, true)) {
                    self::fail($m ?: 'Failed asserting that array contains ' . self::export($needle) . '.');
                }
            }

            protected static function assertArrayHasKey($key, array $array, string $m = ''): void
            {
                if (!array_key_exists($key, $array)) {
                    self::fail($m ?: "Failed asserting that array has key " . self::export($key) . '.');
                }
            }

            protected static function assertIsArray($v, string $m = ''): void
            {
                if (!is_array($v)) {
                    self::fail($m ?: 'Failed asserting that value is an array.');
                }
            }

            protected static function assertIsInt($v, string $m = ''): void
            {
                if (!is_int($v)) {
                    self::fail($m ?: 'Failed asserting that value is an int.');
                }
            }

            protected static function assertGreaterThan($bound, $actual, string $m = ''): void
            {
                if (!($actual > $bound)) {
                    self::fail($m ?: "Failed asserting that $actual is greater than $bound.");
                }
            }

            protected static function assertGreaterThanOrEqual($bound, $actual, string $m = ''): void
            {
                if (!($actual >= $bound)) {
                    self::fail($m ?: "Failed asserting that $actual is >= $bound.");
                }
            }

            protected static function assertLessThan($bound, $actual, string $m = ''): void
            {
                if (!($actual < $bound)) {
                    self::fail($m ?: "Failed asserting that $actual is less than $bound.");
                }
            }

            protected static function assertLessThanOrEqual($bound, $actual, string $m = ''): void
            {
                if (!($actual <= $bound)) {
                    self::fail($m ?: "Failed asserting that $actual is <= $bound.");
                }
            }

            private static function export($v): string
            {
                if (is_bool($v)) {
                    return $v ? 'true' : 'false';
                }
                if (is_array($v)) {
                    return 'array(' . count($v) . ')';
                }
                if (is_null($v)) {
                    return 'null';
                }
                return (string) $v;
            }
        }
    }
}
