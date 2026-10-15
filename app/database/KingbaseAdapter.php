<?php
declare(strict_types=1);

namespace app\database;

use PDO;
use Exception;

class KingbaseAdapter implements DatabaseAdapterInterface
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getDriverName(): string
    {
        return 'kingbase';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function testConnection(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT version() as v, current_database() as db, current_timestamp as cur_time");
            $info = $stmt->fetch();
            return [
                'success' => true,
                'version' => $info['v'] ?? '',
                'database' => $info['db'] ?? '',
                'current_time' => $info['cur_time'] ?? '',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getTables(): array
    {
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'";
        $stmt = $this->pdo->query($sql);
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    public function getTableColumns(string $table): array
    {
        $sql = "SELECT column_name, data_type, is_nullable, column_default, character_maximum_length 
                FROM information_schema.columns 
                WHERE table_schema = 'public' AND table_name = :t";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['t' => $table]);
        $cols = $stmt->fetchAll();
        $result = [];
        foreach ($cols as $col) {
            $result[$col['column_name']] = [
                'name' => $col['column_name'],
                'type' => strtolower($col['data_type']),
                'nullable' => strtoupper($col['is_nullable']) === 'YES',
                'default' => $col['column_default'],
            ];
        }
        return $result;
    }

    public function convertMySqlTypeToKingbase(string $mySqlType): string
    {
        $type = strtolower(trim($mySqlType));
        if (str_starts_with($type, 'int') || str_starts_with($type, 'mediumint')) {
            return 'INTEGER';
        }
        if (str_starts_with($type, 'bigint')) {
            return 'BIGINT';
        }
        if (str_starts_with($type, 'smallint')) {
            return 'SMALLINT';
        }
        if (str_starts_with($type, 'tinyint(1)')) {
            return 'BOOLEAN';
        }
        if (str_starts_with($type, 'tinyint')) {
            return 'SMALLINT';
        }
        if (str_contains($type, 'text') || str_contains($type, 'blob')) {
            return 'TEXT';
        }
        if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
            return 'TIMESTAMP';
        }
        if (str_starts_with($type, 'date')) {
            return 'DATE';
        }
        if (str_starts_with($type, 'varchar') || str_starts_with($type, 'char')) {
            return strtoupper($type);
        }
        if (str_starts_with($type, 'decimal') || str_starts_with($type, 'numeric')) {
            return strtoupper($type);
        }
        return 'VARCHAR(255)';
    }

    public function generateCreateTableSql(string $table, array $columns, array $indexes = []): string
    {
        $colDefs = [];
        $priKey = null;

        foreach ($columns as $name => $col) {
            $isAuto = !empty($col['auto_increment']);
            $rawType = $col['type'] ?? 'varchar(255)';
            $isBig = str_contains(strtolower($rawType), 'bigint');

            if ($isAuto) {
                $type = $isBig ? 'BIGSERIAL' : 'SERIAL';
                $priKey = $name;
                $colDefs[] = "{$this->quoteIdentifier($name)} {$type}";
                continue;
            }

            $type = $this->convertMySqlTypeToKingbase($rawType);
            $nullStr = ($col['nullable'] ?? true) ? 'NULL' : 'NOT NULL';
            $defaultStr = '';
            if (isset($col['default'])) {
                $def = (string)$col['default'];
                if (str_contains(strtoupper($def), 'CURRENT_TIMESTAMP')) {
                    $defaultStr = "DEFAULT CURRENT_TIMESTAMP";
                } elseif (is_numeric($def)) {
                    $defaultStr = "DEFAULT {$def}";
                } else {
                    $defaultStr = "DEFAULT '" . addslashes($def) . "'";
                }
            }

            $colDefs[] = trim("{$this->quoteIdentifier($name)} {$type} {$nullStr} {$defaultStr}");
        }

        if ($priKey) {
            $colDefs[] = "PRIMARY KEY ({$this->quoteIdentifier($priKey)})";
        }

        $createSql = "CREATE TABLE IF NOT EXISTS {$this->quoteIdentifier($table)} (\n  "
            . implode(",\n  ", $colDefs)
            . "\n);";

        $indexSqls = [];
        foreach ($indexes as $idx) {
            $idxName = $table . '_' . $idx['name'];
            $idxCols = implode(',', array_map(fn($c) => $this->quoteIdentifier($c), $idx['columns']));
            if (!empty($idx['unique'])) {
                $indexSqls[] = "CREATE UNIQUE INDEX IF NOT EXISTS {$this->quoteIdentifier($idxName)} ON {$this->quoteIdentifier($table)} ({$idxCols});";
            } else {
                $indexSqls[] = "CREATE INDEX IF NOT EXISTS {$this->quoteIdentifier($idxName)} ON {$this->quoteIdentifier($table)} ({$idxCols});";
            }
        }

        if (!empty($indexSqls)) {
            $createSql .= "\n" . implode("\n", $indexSqls);
        }

        return $createSql;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $table, array $data): int|string
    {
        $cols = array_keys($data);
        $quotedCols = array_map(fn($c) => $this->quoteIdentifier($c), $cols);
        $placeholders = array_map(fn($c) => ":{$c}", $cols);

        $sql = "INSERT INTO {$this->quoteIdentifier($table)} (" . implode(', ', $quotedCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    public function batchInsert(string $table, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $cols = array_keys(reset($rows));
        $quotedCols = array_map(fn($c) => $this->quoteIdentifier($c), $cols);

        $valPlaceholders = [];
        $flatParams = [];
        foreach ($rows as $rowIndex => $row) {
            $rowPlaceholders = [];
            foreach ($cols as $col) {
                $key = "p_{$rowIndex}_{$col}";
                $rowPlaceholders[] = ":{$key}";
                $flatParams[$key] = $row[$col] ?? null;
            }
            $valPlaceholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = "INSERT INTO {$this->quoteIdentifier($table)} (" . implode(', ', $quotedCols) . ") VALUES " . implode(', ', $valPlaceholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($flatParams);
        return $stmt->rowCount();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function resetAutoIncrement(string $table, int $maxId): void
    {
        $seq = "{$table}_id_seq";
        $this->pdo->exec("SELECT setval('{$seq}', {$maxId}, true)");
    }
}
