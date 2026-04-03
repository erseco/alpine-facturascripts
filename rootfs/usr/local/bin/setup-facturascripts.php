#!/usr/bin/php84
<?php

/**
 * FacturaScripts Wizard Auto-Setup Script.
 *
 * Completes the FacturaScripts installation wizard programmatically,
 * replacing the need for manual browser-based setup. Equivalent to
 * alpine-omeka-s's install_cli.php.
 *
 * Environment variables:
 *   FS_CODPAIS              Country code (default: ESP)
 *   FS_COMPANY_NAME         Company name (default: Mi Empresa)
 *   FS_COMPANY_CIF          Company tax ID (default: empty)
 *   FS_COMPANY_REGIMENIVA   VAT regime (default: General)
 *   FS_LOAD_ACCOUNTING_PLAN Load default accounting plan (default: true)
 *
 * Usage: php84 setup-facturascripts.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('FS_FOLDER', '/var/www/html');

$configFile = FS_FOLDER . '/config.php';
if (!file_exists($configFile)) {
    echo "[setup] config.php not found. Skipping.\n";
    exit(0);
}

require_once FS_FOLDER . '/vendor/autoload.php';
require_once $configFile;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;

Kernel::init();
Plugins::deploy(true, true);

$db = new DataBase();
if (!$db->connect()) {
    fwrite(STDERR, "[setup] Cannot connect to database.\n");
    exit(1);
}

// Check if Wizard already completed (admin homepage != Wizard)
$users = $db->select("SELECT homepage FROM users WHERE nick = 'admin'");
if (!empty($users) && ($users[0]['homepage'] ?? 'Wizard') !== 'Wizard') {
    echo "[setup] FacturaScripts already set up. Skipping.\n";
    exit(0);
}

echo "[setup] Completing FacturaScripts Wizard...\n";

// Step 1: Initialize core models (Wizard Step 1)
$coreModels = [
    'AttachedFile', 'Diario', 'EstadoDocumento', 'FormaPago',
    'Impuesto', 'Retencion', 'Serie', 'Provincia',
];
foreach ($coreModels as $name) {
    $cls = '\\FacturaScripts\\Dinamic\\Model\\' . $name;
    if (class_exists($cls)) {
        new $cls();
    }
}

// Step 2: Set country defaults
$codpais = getenv('FS_CODPAIS') ?: 'ESP';
$filePath = FS_FOLDER . '/Dinamic/Data/Codpais/' . $codpais . '/default.json';
if (file_exists($filePath)) {
    $defaults = json_decode(file_get_contents($filePath), true) ?? [];
    foreach ($defaults as $group => $values) {
        foreach ($values as $key => $value) {
            Tools::settingsSet($group, $key, $value);
        }
    }
}
Tools::settingsSet('default', 'codpais', $codpais);
Tools::settingsSet('default', 'homepage', 'Dashboard');
Tools::settingsSave();
echo "[setup] Country defaults set: {$codpais}\n";

// Step 3: Configure company
$empresa = new \FacturaScripts\Dinamic\Model\Empresa();
if ($empresa->loadFromCode(1)) {
    $empresa->nombre = getenv('FS_COMPANY_NAME') ?: 'Mi Empresa';
    $empresa->nombrecorto = $empresa->nombre;
    $empresa->cifnif = getenv('FS_COMPANY_CIF') ?: '';
    $empresa->codpais = $codpais;
    $empresa->regimeniva = getenv('FS_COMPANY_REGIMENIVA') ?: 'General';
    $empresa->save();
    echo "[setup] Company: {$empresa->nombre}\n";
}

// Step 4: Update warehouse country
$almacen = new \FacturaScripts\Dinamic\Model\Almacen();
foreach ($almacen->all() as $alm) {
    $alm->codpais = $codpais;
    $alm->save();
}

// Step 5: Initialize ALL Dinamic models (Wizard Step 3 — creates all tables)
$dinamicModelDir = FS_FOLDER . '/Dinamic/Model';
if (is_dir($dinamicModelDir)) {
    foreach (scandir($dinamicModelDir) as $file) {
        if (substr($file, -4) === '.php') {
            $cls = '\\FacturaScripts\\Dinamic\\Model\\' . substr($file, 0, -4);
            if (class_exists($cls)) {
                try {
                    new $cls();
                } catch (\Throwable $e) {
                    // Skip models that fail to instantiate
                }
            }
        }
    }
}

// Step 6: Re-deploy to register routes for all models
Plugins::deploy(true, true);

// Step 7: Set admin homepage to Dashboard
$db->exec("UPDATE users SET homepage = 'Dashboard' WHERE nick = 'admin'");
echo "[setup] Admin homepage set to Dashboard.\n";

// Step 8: Load default accounting plan
$loadPlan = getenv('FS_LOAD_ACCOUNTING_PLAN');
if ($loadPlan === false || $loadPlan === '' || $loadPlan === 'true' || $loadPlan === '1') {
    $planFile = FS_FOLDER . '/Dinamic/Data/Codpais/' . $codpais . '/defaultPlan.csv';
    if (file_exists($planFile)) {
        $ejercicios = $db->select('SELECT codejercicio FROM ejercicios LIMIT 1');
        if (!empty($ejercicios)) {
            $cls = '\\FacturaScripts\\Dinamic\\Lib\\Accounting\\AccountingPlanImport';
            if (class_exists($cls)) {
                (new $cls())->importCSV($planFile, $ejercicios[0]['codejercicio']);
                echo "[setup] Accounting plan loaded.\n";
            }
        }
    }
}

echo "[setup] FacturaScripts setup completed.\n";
