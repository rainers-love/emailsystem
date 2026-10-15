<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use app\database\DatabaseManager;
use app\service\MigrationService;

try {
    $adapter = DatabaseManager::getAdapter();
    $migrationService = new MigrationService($adapter);
    $executed = $migrationService->runCoreMigrations();
    echo "MIGRATION COMPLETED! Created tables: " . implode(', ', $executed) . "\n";
    $tables = $adapter->getTables();
    echo "Current tables in DB:\n - " . implode("\n - ", $tables) . "\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
