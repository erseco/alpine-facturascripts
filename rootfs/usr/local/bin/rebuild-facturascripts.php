#!/usr/bin/php84
<?php
/**
 * FacturaScripts Rebuild Script
 *
 * This script rebuilds FacturaScripts by deploying Dinamic classes and cache.
 * Usage: php84 rebuild-facturascripts.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.' . PHP_EOL);
}

// Define FS_FOLDER constant
define('FS_FOLDER', '/var/www/html');

// Load FacturaScripts configuration
$configFile = FS_FOLDER . '/config.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "Error: config.php not found. FacturaScripts must be configured first." . PHP_EOL);
    exit(1);
}

require_once $configFile;

// Load Composer autoload
$autoloadFile = FS_FOLDER . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    fwrite(STDERR, "Error: Composer autoload not found." . PHP_EOL);
    exit(1);
}

require_once $autoloadFile;

use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Plugins;

try {
    echo "Initializing FacturaScripts Kernel..." . PHP_EOL;

    // Initialize FacturaScripts Kernel
    Kernel::init();

    echo "Rebuilding FacturaScripts (deploying Dinamic classes and cache)..." . PHP_EOL;

    // Rebuild: deploy Dinamic, routes and cache
    // Parameters: rebuildRoutes=true, clearCache=true
    Plugins::deploy(true, true);

    echo "FacturaScripts rebuild completed successfully." . PHP_EOL;
    exit(0);

} catch (Exception $e) {
    fwrite(STDERR, "Error during rebuild: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Stack trace: " . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
