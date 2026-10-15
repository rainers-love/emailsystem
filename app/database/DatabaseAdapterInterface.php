<?php
declare(strict_types=1);

namespace app\database;

use PDO;

interface DatabaseAdapterInterface
{
    public function getDriverName(): string;
    public function quoteIdentifier(string $identifier): string;
    public function testConnection(): array;
    public function getTables(): array;
    public function getTableColumns(string $table): array;
    public function generateCreateTableSql(string $table, array $columns, array $indexes = []): string;
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): int;
    public function insert(string $table, array $data): int|string;
    public function batchInsert(string $table, array $rows): int;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollBack(): bool;
    public function resetAutoIncrement(string $table, int $maxId): void;
}
