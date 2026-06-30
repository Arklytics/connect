<?php

declare(strict_types=1);

namespace Illuminate\Database\Migrations {
    abstract class Migration
    {
        public function up(): void
        {
        }

        public function down(): void
        {
        }
    }
}

namespace Illuminate\Database\Schema {
    final class ColumnDefinition
    {
        public function __construct(
            public string $name,
            public string $type,
            public ?int $length = null,
        ) {
        }

        public bool $nullable = false;
        public mixed $default = null;
        public ?string $after = null;
        public bool $autoIncrement = false;
        public bool $primary = false;
        public bool $unique = false;

        public function nullable(bool $value = true): self
        {
            $this->nullable = $value;
            return $this;
        }

        public function default(mixed $value): self
        {
            $this->default = $value;
            return $this;
        }

        public function after(string $column): self
        {
            $this->after = $column;
            return $this;
        }

        public function autoIncrement(bool $value = true): self
        {
            $this->autoIncrement = $value;
            return $this;
        }

        public function primary(bool $value = true): self
        {
            $this->primary = $value;
            return $this;
        }

        public function unique(bool $value = true): self
        {
            $this->unique = $value;
            return $this;
        }
    }

    final class Blueprint
    {
        /** @var array<int, ColumnDefinition> */
        private array $columns = [];
        /** @var array<int, string> */
        private array $dropColumns = [];
        /** @var array<int, array<int, string>> */
        private array $indexes = [];

        public function __construct(
            private string $table,
            private bool $creating = false,
        ) {
        }

        public function id(string $column = 'id'): ColumnDefinition
        {
            $definition = $this->addColumn($column, 'bigint');
            $definition->autoIncrement()->primary();

            return $definition;
        }

        public function bigIncrements(string $column = 'id'): ColumnDefinition
        {
            return $this->id($column);
        }

        public function unsignedBigInteger(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'bigint')->default(null);
        }

        public function unsignedInteger(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'int');
        }

        public function unsignedSmallInteger(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'smallint');
        }

        public function integer(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'int');
        }

        public function string(string $column, int $length = 255): ColumnDefinition
        {
            return $this->addColumn($column, 'varchar', $length);
        }

        public function text(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'text');
        }

        public function mediumText(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'mediumtext');
        }

        public function longText(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'longtext');
        }

        public function timestamp(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'timestamp');
        }

        public function boolean(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'tinyint');
        }

        public function json(string $column): ColumnDefinition
        {
            return $this->addColumn($column, 'json');
        }

        public function timestamps(): void
        {
            $this->timestamp('created_at')->nullable();
            $this->timestamp('updated_at')->nullable();
        }

        public function dropColumn(string|array $columns): void
        {
            foreach ((array) $columns as $column) {
                $this->dropColumns[] = (string) $column;
            }
        }

        public function index(string|array $columns): void
        {
            $this->indexes[] = array_map('strval', (array) $columns);
        }

        /** @return array<int, ColumnDefinition> */
        public function columns(): array
        {
            return $this->columns;
        }

        /** @return array<int, string> */
        public function dropColumns(): array
        {
            return $this->dropColumns;
        }

        private function addColumn(string $column, string $type, ?int $length = null): ColumnDefinition
        {
            $definition = new ColumnDefinition($column, $type, $length);
            $this->columns[] = $definition;

            return $definition;
        }

        public function toCreateStatements(): array
        {
            $columns = array_map(
                fn (ColumnDefinition $column): string => $this->compileColumnDefinition($column, true),
                $this->columns
            );
            $indexes = $this->compileIndexes();

            return [
                sprintf(
                    'CREATE TABLE IF NOT EXISTS `%s` (%s%s) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                    $this->table,
                    implode(', ', $columns),
                    $indexes !== [] ? ', ' . implode(', ', $indexes) : ''
                ),
            ];
        }

        public function toAlterStatements(): array
        {
            $statements = [];

            foreach ($this->columns as $column) {
                $statements[] = sprintf(
                    'ALTER TABLE `%s` ADD COLUMN %s',
                    $this->table,
                    $this->compileColumnDefinition($column, false)
                );
            }

            foreach ($this->dropColumns as $column) {
                $statements[] = sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $this->table, $column);
            }

            foreach ($this->columns as $column) {
                if ($column->unique) {
                    $statements[] = sprintf(
                        'ALTER TABLE `%s` ADD UNIQUE KEY `%s_%s_unique` (`%s`)',
                        $this->table,
                        $this->table,
                        $column->name,
                        $column->name
                    );
                }
            }

            foreach ($this->indexes as $columns) {
                $indexName = $this->table . '_' . implode('_', $columns) . '_index';
                $statements[] = sprintf(
                    'ALTER TABLE `%s` ADD KEY `%s` (`%s`)',
                    $this->table,
                    $indexName,
                    implode('`, `', $columns)
                );
            }

            return $statements;
        }

        /**
         * @return array<int, string>
         */
        private function compileIndexes(): array
        {
            $indexes = [];

            foreach ($this->columns as $column) {
                if ($column->unique) {
                    $indexes[] = sprintf('UNIQUE KEY `%s_%s_unique` (`%s`)', $this->table, $column->name, $column->name);
                }
            }

            foreach ($this->indexes as $columns) {
                $indexName = $this->table . '_' . implode('_', $columns) . '_index';
                $indexes[] = sprintf(
                    'KEY `%s` (`%s`)',
                    $indexName,
                    implode('`, `', $columns)
                );
            }

            return $indexes;
        }

        private function compileColumnDefinition(ColumnDefinition $column, bool $forCreate): string
        {
            $sql = sprintf('`%s` %s', $column->name, $this->compileType($column));

            if ($column->autoIncrement) {
                $sql .= ' NOT NULL AUTO_INCREMENT';
            } elseif ($column->nullable) {
                $sql .= ' NULL';
            } else {
                $sql .= ' NOT NULL';
            }

            if ($column->default !== null) {
                $sql .= ' DEFAULT ' . $this->compileDefault($column->default);
            } elseif ($column->nullable) {
                $sql .= ' DEFAULT NULL';
            }

            if ($column->primary) {
                $sql .= ' PRIMARY KEY';
            }

            if (!$forCreate && $column->after !== null) {
                $sql .= sprintf(' AFTER `%s`', $column->after);
            }

            return $sql;
        }

        private function compileType(ColumnDefinition $column): string
        {
            return match ($column->type) {
                'bigint' => 'BIGINT UNSIGNED',
                'int' => 'INT UNSIGNED',
                'smallint' => 'SMALLINT UNSIGNED',
                'varchar' => sprintf('VARCHAR(%d)', $column->length ?? 255),
                'text' => 'TEXT',
                'mediumtext' => 'MEDIUMTEXT',
                'longtext' => 'LONGTEXT',
                'timestamp' => 'TIMESTAMP',
                'tinyint' => 'TINYINT(1)',
                'json' => 'JSON',
                default => strtoupper($column->type),
            };
        }

        private function compileDefault(mixed $value): string
        {
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            if ($value === null) {
                return 'NULL';
            }

            if (is_string($value) && strtoupper($value) === 'CURRENT_TIMESTAMP') {
                return 'CURRENT_TIMESTAMP';
            }

            return "'" . addslashes((string) $value) . "'";
        }
    }
}

namespace Illuminate\Support\Facades {
    use Illuminate\Database\Schema\Blueprint;
    use mysqli;
    use RuntimeException;

    final class Schema
    {
        private static ?mysqli $connection = null;

        public static function setConnection(mysqli $connection): void
        {
            self::$connection = $connection;
        }

        public static function create(string $table, callable $callback): void
        {
            $blueprint = new Blueprint($table, true);
            $callback($blueprint);
            self::runStatements($blueprint->toCreateStatements());
        }

        public static function table(string $table, callable $callback): void
        {
            $blueprint = new Blueprint($table, false);
            $callback($blueprint);
            self::runStatements($blueprint->toAlterStatements());
        }

        public static function dropIfExists(string $table): void
        {
            self::connection()->query(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }

        public static function hasTable(string $table): bool
        {
            $result = self::connection()->query(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . self::connection()->real_escape_string($table) . "' LIMIT 1"
            );

            return (bool) ($result && $result->num_rows > 0);
        }

        /** @return array<int, string> */
        public static function getColumnListing(string $table): array
        {
            if (!self::hasTable($table)) {
                return [];
            }

            $result = self::connection()->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
            $columns = [];
            while ($row = $result?->fetch_assoc()) {
                $columns[] = (string) $row['Field'];
            }

            return $columns;
        }

        public static function hasColumn(string $table, string $column): bool
        {
            return in_array($column, self::getColumnListing($table), true);
        }

        private static function connection(): mysqli
        {
            if (!self::$connection instanceof mysqli) {
                throw new RuntimeException('Schema connection has not been initialized.');
            }

            return self::$connection;
        }

        private static function runStatements(array $statements): void
        {
            foreach ($statements as $statement) {
                self::connection()->query($statement);
            }
        }
    }
}
