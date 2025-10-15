<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: PHPUnit Bootstrap file for test initialization
 */

// Define test environment constants
define('PCA_TEST_ROOT', __DIR__);
define('PCA_PROJECT_ROOT', dirname(__DIR__));
define('PCA_TEST_FIXTURES', PCA_TEST_ROOT . '/fixtures');
define('PCA_TEST_OUTPUT', PCA_TEST_ROOT . '/output');

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set memory limit for tests
ini_set('memory_limit', '512M');

// Set timezone
date_default_timezone_set('UTC');

// Find and load autoloader
$autoloaderPaths = [
    PCA_PROJECT_ROOT . '/vendor/autoload.php',
    PCA_PROJECT_ROOT . '/../vendor/autoload.php',
    PCA_PROJECT_ROOT . '/../../vendor/autoload.php',
    PCA_PROJECT_ROOT . '/../../../autoload.php'
];

$autoloaderFound = false;
foreach ($autoloaderPaths as $autoloaderPath) {
    if (file_exists($autoloaderPath)) {
        require_once $autoloaderPath;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    // Fallback: Manual autoloader for test environment
    spl_autoload_register(function (string $class) {
        $prefix = 'YcPca\\';
        $baseDir = PCA_PROJECT_ROOT . '/src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
    
    // Also register test autoloader
    spl_autoload_register(function (string $class) {
        $prefix = 'YcPca\\Tests\\';
        $baseDir = PCA_TEST_ROOT . '/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Create necessary test directories
$testDirectories = [
    PCA_TEST_FIXTURES,
    PCA_TEST_OUTPUT,
    PCA_TEST_ROOT . '/logs',
    PCA_PROJECT_ROOT . '/coverage'
];

foreach ($testDirectories as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

// Load test helpers and utilities
require_once PCA_TEST_ROOT . '/TestCase.php';
require_once PCA_TEST_ROOT . '/Helpers/TestHelper.php';

// Set up global test configuration
$GLOBALS['PCA_TEST_CONFIG'] = [
    'fixtures_path' => PCA_TEST_FIXTURES,
    'output_path' => PCA_TEST_OUTPUT,
    'memory_limit' => '512M',
    'execution_timeout' => 30,
    'enable_coverage' => true,
    'mock_external_services' => true
];

// Initialize test environment
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    echo "PHPUnit test environment initialized successfully.\n";
    echo "Test root: " . PCA_TEST_ROOT . "\n";
    echo "Project root: " . PCA_PROJECT_ROOT . "\n";
    echo "Fixtures path: " . PCA_TEST_FIXTURES . "\n";
}