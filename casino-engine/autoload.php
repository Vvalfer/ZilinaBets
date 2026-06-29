<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader for the CasinoEngine namespace.
 *
 * In production you would use Composer's autoloader (vendor/autoload.php).
 * This file lets the engine and its reference API run with zero install,
 * which is handy in CI, in the grading sandbox, and for a quick demo.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'CasinoEngine\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
