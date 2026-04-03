#!/usr/bin/php84
<?php

/**
 * FacturaScripts Seed Data Loader.
 *
 * Loads seed data from a JSON file into FacturaScripts using Dinamic models.
 * Equivalent to alpine-omeka-s's import_cli.php.
 *
 * JSON format:
 *   {
 *     "seed": {
 *       "Proveedor": [
 *         {"nombre": "Acme", "cifnif": "B12345678", "_unique": "cifnif"}
 *       ],
 *       "Producto": [
 *         {"referencia": "PROD-1", "descripcion": "Widget", "_unique": "referencia"}
 *       ]
 *     }
 *   }
 *
 * Each key under "seed" must be a valid FacturaScripts model name.
 * The optional "_unique" field specifies which field to use for duplicate checking.
 * If "_unique" is not set, the first field in the record is used.
 *
 * Also supports the AiScan blueprint format:
 *   {
 *     "seed": {
 *       "suppliers": [...],   -> maps to Proveedor
 *       "products": [...]     -> maps to Producto
 *     }
 *   }
 *
 * Environment variables:
 *   FS_SEED_FILE    Path to the JSON seed file
 *
 * Usage: php84 seed-facturascripts.php <path-to-json>
 *        php84 seed-facturascripts.php (reads FS_SEED_FILE env var)
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('FS_FOLDER', '/var/www/html');

$configFile = FS_FOLDER . '/config.php';
if (!file_exists($configFile)) {
    echo "[seed] config.php not found. Skipping.\n";
    exit(0);
}

require_once FS_FOLDER . '/vendor/autoload.php';
require_once $configFile;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Where;

Kernel::init();
Plugins::deploy(true, true);

$db = new DataBase();
if (!$db->connect()) {
    fwrite(STDERR, "[seed] Cannot connect to database.\n");
    exit(1);
}

// Resolve seed file path
$seedFile = $argv[1] ?? getenv('FS_SEED_FILE') ?: '';
if (empty($seedFile) || !file_exists($seedFile)) {
    echo "[seed] No seed file found. Skipping.\n";
    exit(0);
}

$data = json_decode(file_get_contents($seedFile), true);
if (!is_array($data) || empty($data['seed'])) {
    echo "[seed] No 'seed' key in JSON file. Skipping.\n";
    exit(0);
}

echo "[seed] Loading seed data from: {$seedFile}\n";

// Alias mapping for convenience keys
$aliases = [
    'suppliers' => 'Proveedor',
    'products' => 'Producto',
    'customers' => 'Cliente',
];

$seed = $data['seed'];
$totalCreated = 0;
$totalSkipped = 0;

foreach ($seed as $key => $records) {
    if (!is_array($records) || empty($records)) {
        continue;
    }

    // Resolve model name from alias or direct name
    $modelName = $aliases[$key] ?? $key;
    $className = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;

    if (!class_exists($className)) {
        echo "[seed] Model not found: {$modelName}. Skipping.\n";
        continue;
    }

    echo "[seed] Loading {$modelName} (" . count($records) . " records)...\n";

    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        // Determine unique field for duplicate check
        $uniqueField = $record['_unique'] ?? null;
        unset($record['_unique']);

        if ($uniqueField === null) {
            // Auto-detect: use first non-empty string field
            foreach ($record as $field => $value) {
                if (is_string($value) && !empty($value)) {
                    $uniqueField = $field;
                    break;
                }
            }
        }

        // Check for duplicates
        if ($uniqueField && isset($record[$uniqueField])) {
            $model = new $className();
            $where = [Where::eq($uniqueField, $record[$uniqueField])];
            if ($model->loadWhere($where)) {
                $label = $record[$uniqueField];
                echo "  Exists: {$label}\n";
                $totalSkipped++;
                continue;
            }
        }

        // Create the record
        $model = new $className();
        foreach ($record as $field => $value) {
            if (property_exists($model, $field)) {
                $model->$field = $value;
            }
        }

        $label = $record[$uniqueField ?? array_key_first($record)] ?? '?';
        if ($model->save()) {
            echo "  Created: {$label}\n";
            $totalCreated++;
        } else {
            echo "  FAILED: {$label}\n";
        }
    }
}

echo "[seed] Done: {$totalCreated} created, {$totalSkipped} skipped.\n";
