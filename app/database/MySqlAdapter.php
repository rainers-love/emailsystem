<?php
declare(strict_types=1);

namespace app\database;

use PDO;
use Exception;

class MySqlAdapter implements DatabaseAdapterInterface
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getDriverName(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function testConnection(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT VERSION() as v, DATABASE() as db, NOW() as cur_time");
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
        $stmt = $this->pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    public function getTableColumns(string $table): array
    {
        $stmt = $this->pdo->prepare("SHOW FULL COLUMNS FROM " . $this->quoteIdentifier($table));
        $stmt->execute();
        $cols = $stmt->fetchAll();
        $result = [];
        foreach ($cols as $col) {
            $result[$col['Field']] = [
                'name' => $col['Field'],
                'type' => strtolower($col['Type']),
                'nullable' => strtoupper($col['Null']) === 'YES',
                'key' => $col['Key'],
                'default' => $col['Default'],
                'extra' => $col['Extra'],
                'comment' => $col['Comment'] ?? '',
            ];
        }
        return $result;
    }

    public function generateCreateTableSql(string $table, array $columns, array $indexes = []): string
    {
        $colDefs = [];
        $priKey = null;

        foreach ($columns as $name => $col) {
            $type = strtoupper($col['type']);
            $isAuto = !empty($col['auto_increment']);
            $nullStr = ($isAuto || !($col['nullable'] ?? true)) ? 'NOT NULL' : 'NULL';
            $defaultStr = '';
            if ($isAuto) {
                $priKey = $name;
                $extra = 'AUTO_INCREMENT';
            } else {
                $extra = '';
                if (isset($col['default'])) {
                    $def = (string)$col['default'];
                    if (str_contains(strtoupper($def), 'CURRENT_TIMESTAMP')) {
                        $defaultStr = "DEFAULT CURRENT_TIMESTAMP";
                    } elseif (is_numeric($def)) {
                        $defaultStr = "DEFAULT {$def}";
                    } else {
                        $defaultStr = "DEFAULT '" . addslashes($def) . "'";
                    }
                } elseif (($col['nullable'] ?? true) && !str_contains($type, 'TEXT') && !str_contains($type, 'BLOB')) {
                    $defaultStr = "DEFAULT NULL";
                }
            }

            $commentStr = !empty($col['comment']) ? "COMMENT '" . addslashes($col['comment']) . "'" : "";

            $colDefs[] = trim("{$this->quoteIdentifier($name)} {$type} {$nullStr} {$defaultStr} {$extra} {$commentStr}");
        }

        if ($priKey) {
            $colDefs[] = "PRIMARY KEY ({$this->quoteIdentifier($priKey)})";
        }

        foreach ($indexes as $idx) {
            $idxName = $idx['name'];
            $idxCols = implode(',', array_map(fn($c) => $this->quoteIdentifier($c), $idx['columns']));
            if (!empty($idx['unique'])) {
                $colDefs[] = "UNIQUE KEY {$this->quoteIdentifier($idxName)} ({$idxCols})";
            } else {
                $colDefs[] = "KEY {$this->quoteIdentifier($idxName)} ({$idxCols})";
            }
        }

        return "CREATE TABLE IF NOT EXISTS {$this->quoteIdentifier($table)} (\n  "
            . implode(",\n  ", $colDefs)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
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
        $next = $maxId + 1;
        $this->pdo->exec("ALTER TABLE {$this->quoteIdentifier($table)} AUTO_INCREMENT = {$next}");
    }
}
