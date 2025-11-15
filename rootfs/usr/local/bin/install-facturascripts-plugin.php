#!/usr/bin/php84
<?php
/**
 * FacturaScripts Plugin Installer
 *
 * This script installs and enables a FacturaScripts plugin from a ZIP file.
 * Usage: php84 install-facturascripts-plugin.php /path/to/plugin.zip
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.' . PHP_EOL);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: {$argv[0]} <plugin.zip>" . PHP_EOL);
    exit(1);
}

$pluginZip = $argv[1];

if (!file_exists($pluginZip)) {
    fwrite(STDERR, "Error: Plugin file not found: {$pluginZip}" . PHP_EOL);
    exit(1);
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

    echo "Installing plugin from: {$pluginZip}" . PHP_EOL;

    // Install the plugin
    $result = Plugins::add($pluginZip);

    if (!$result) {
        fwrite(STDERR, "Error: Failed to install plugin from {$pluginZip}" . PHP_EOL);
        exit(1);
    }

    echo "Plugin installed successfully." . PHP_EOL;

    // Extract plugin name from the ZIP to enable it
    $pluginName = getPluginNameFromZip($pluginZip);

    if ($pluginName) {
        echo "Enabling plugin: {$pluginName}" . PHP_EOL;

        // Enable the plugin
        $enableResult = Plugins::enable($pluginName);

        if (!$enableResult) {
            fwrite(STDERR, "Warning: Plugin installed but failed to enable: {$pluginName}" . PHP_EOL);
            // Don't exit with error, as the plugin is installed
        } else {
            echo "Plugin enabled successfully: {$pluginName}" . PHP_EOL;
        }
    } else {
        echo "Warning: Could not determine plugin name to enable it." . PHP_EOL;
    }

    echo "Plugin installation completed." . PHP_EOL;
    exit(0);

} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Stack trace: " . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}

/**
 * Extract plugin name from ZIP file by reading facturascripts.ini
 *
 * @param string $zipPath Path to the ZIP file
 * @return string|null Plugin name or null if not found
 */
function getPluginNameFromZip($zipPath) {
    $zip = new ZipArchive();

    if ($zip->open($zipPath) !== true) {
        return null;
    }

    // Look for facturascripts.ini file
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);

        if (basename($filename) === 'facturascripts.ini') {
            $content = $zip->getFromIndex($i);
            $zip->close();

            // Parse INI content
            $ini = parse_ini_string($content);

            if (isset($ini['name'])) {
                return $ini['name'];
            }

            // Alternative: extract from directory name
            $parts = explode('/', $filename);
            if (count($parts) > 1) {
                return $parts[0];
            }

            break;
        }
    }

    $zip->close();
    return null;
}
