<?php
declare(strict_types=1);

namespace app\database;

use Exception;

class SchemaManager
{
    protected DatabaseAdapterInterface $adapter;

    public function __construct(DatabaseAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function initMigrationsTable(): void
    {
        $columns = [
            'id' => ['type' => 'int(11)', 'auto_increment' => true],
            'version' => ['type' => 'varchar(64)', 'nullable' => false],
            'description' => ['type' => 'varchar(255)', 'nullable' => false],
            'executed_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
        ];
        $indexes = [
            ['name' => 'uniq_version', 'columns' => ['version'], 'unique' => true]
        ];

        $sql = $this->adapter->generateCreateTableSql('sys_schema_migrations', $columns, $indexes);
        $this->adapter->execute($sql);
    }

    public function hasMigration(string $version): bool
    {
        try {
            $t = $this->adapter->quoteIdentifier('sys_schema_migrations');
            $v = $this->adapter->quoteIdentifier('version');
            $rows = $this->adapter->query("SELECT {$v} FROM {$t} WHERE {$v} = :version LIMIT 1", ['version' => $version]);
            return !empty($rows);
        } catch (Exception $e) {
            return false;
        }
    }

    public function recordMigration(string $version, string $desc): void
    {
        $this->adapter->insert('sys_schema_migrations', [
            'version' => $version,
            'description' => $desc,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
