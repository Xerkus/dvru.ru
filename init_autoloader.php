<?php
/**
 * Autoloading initializer.
 * This file is not really needed as i use composer to manage dependencies and
 * autoloading
 */

// Composer autoloading
if (!file_exists('vendor/autoload.php')) {
    throw new RuntimeException('Dependencies are not installed. Run `composer install`.');
}
$loader = include 'vendor/autoload.php';

