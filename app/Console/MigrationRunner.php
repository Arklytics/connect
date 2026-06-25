<?php

declare(strict_types=1);

namespace App\Console;

use Config;
use Illuminate\Support\Facades\Schema;
use mysqli;
use mysqli_sql_exception;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private string $basePath,
    ) {
    }

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $options = array_slice($argv, 2);

        return match ($command) {
            'migrate' => $this->runMigrations(in_array('--seed', $options, true)),
            'migrate:status' => $this->showStatus(),
            'migrate:rollback' => $this->rollback(),
            'db:seed' => $this->seed(),
            'make:migration' => $this->makeMigration($options),
            'help', '--help', '-h' => $this->help(),
            default => $this->unknownCommand($command),
        };
    }

    private function runMigrations(bool $seed = false): int
    {
        $db = $this->connect();
        $this->ensureRepositoryTable($db);
        Schema::setConnection($db);

        $applied = $this->appliedMigrations($db);
        $files = $this->migrationFiles();
        $batch = $this->nextBatchNumber($db);
        $ran = 0;

        foreach ($files as $file) {
            $migrationName = basename($file);
            if (isset($applied[$migrationName])) {
                continue;
            }

            $migration = $this->loadMigration($file);
            $migration->up();
            $this->recordMigration($db, $migrationName, $batch);
            $ran++;

            echo sprintf("Migrated: %s\n", $migrationName);
        }

        if ($ran === 0) {
            echo "Nothing to migrate.\n";
        } else {
            echo sprintf("Migration batch %d complete. %d file(s) applied.\n", $batch, $ran);
        }

        if ($seed) {
            return $this->seed();
        }

        return 0;
    }

    private function showStatus(): int
    {
        $db = $this->connect();
        $this->ensureRepositoryTable($db);
        $applied = $this->appliedMigrations($db);

        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);
            $status = isset($applied[$name]) ? 'Ran' : 'Pending';
            echo sprintf("%-10s %s\n", $status, $name);
        }

        return 0;
    }

    private function rollback(): int
    {
        $db = $this->connect();
        $this->ensureRepositoryTable($db);
        Schema::setConnection($db);

        $result = $db->query('SELECT MAX(batch) AS batch_no FROM migrations');
        $row = $result ? $result->fetch_assoc() : null;
        $batch = (int) ($row['batch_no'] ?? 0);

        if ($batch <= 0) {
            echo "Nothing to rollback.\n";
            return 0;
        }

        $rows = $db->query('SELECT migration FROM migrations WHERE batch = ' . $batch . ' ORDER BY id DESC');
        if (!$rows) {
            throw new RuntimeException('Unable to read migration batch.');
        }

        $count = 0;
        while ($row = $rows->fetch_assoc()) {
            $migrationName = (string) $row['migration'];
            $file = $this->migrationPathFor($migrationName);
            if (!is_file($file)) {
                throw new RuntimeException("Migration file not found: {$migrationName}");
            }

            $migration = $this->loadMigration($file);
            $migration->down();
            $db->query(
                "DELETE FROM migrations WHERE migration = '" . $db->real_escape_string($migrationName) . "' LIMIT 1"
            );
            $count++;

            echo sprintf("Rolled back: %s\n", $migrationName);
        }

        echo sprintf("Rollback complete. %d migration(s) reverted from batch %d.\n", $count, $batch);

        return 0;
    }

    private function seed(): int
    {
        $seedFile = $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seed-demo.php';
        if (!is_file($seedFile)) {
            throw new RuntimeException('Seed file not found: database/seed-demo.php');
        }

        require $seedFile;

        return 0;
    }

    private function makeMigration(array $options): int
    {
        $name = null;
        foreach ($options as $option) {
            if (!str_starts_with($option, '--')) {
                $name = $option;
                break;
            }
        }

        if ($name === null || trim($name) === '') {
            throw new RuntimeException('Usage: php artisan make:migration migration_name');
        }

        $timestamp = date('Y_m_d_His');
        $slug = $this->slugify($name);
        $filename = sprintf('%s_%s.php', $timestamp, $slug);
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . $filename;

        if (is_file($path)) {
            throw new RuntimeException("Migration already exists: {$filename}");
        }

        $stub = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
PHP;

        file_put_contents($path, $stub);
        echo sprintf("Created migration: %s\n", $filename);

        return 0;
    }

    private function help(): int
    {
        echo "Available commands:\n";
        echo "  migrate\n";
        echo "  migrate:status\n";
        echo "  migrate:rollback\n";
        echo "  db:seed\n";
        echo "  make:migration <name>\n";

        return 0;
    }

    private function unknownCommand(string $command): int
    {
        fwrite(STDERR, "Unknown command: {$command}\n");
        return 1;
    }

    private function connect(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $hosts = array_values(array_unique(array_filter([
            Config::get('DB_HOST', 'localhost') ?? 'localhost',
            '127.0.0.1',
            'localhost',
        ])));
        $user = Config::get('DB_USER', 'root') ?? 'root';
        $password = Config::get('DB_PASSWORD', '') ?? '';
        $database = Config::get('DB_NAME', 'growthlink') ?? 'growthlink';
        $ports = array_values(array_unique(array_map('intval', array_filter([
            Config::get('DB_PORT', '3306'),
            '3306',
            '3307',
        ], static fn ($value): bool => $value !== null && $value !== ''))));

        $errors = [];
        foreach ($hosts as $host) {
            foreach ($ports as $port) {
                try {
                    $server = mysqli_init();
                    $server->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
                    if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
                        $server->options(MYSQLI_OPT_READ_TIMEOUT, 3);
                    }
                    $server->real_connect($host, $user, $password, '', $port);
                    $server->set_charset('utf8mb4');

                    $server->query(sprintf(
                        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                        $server->real_escape_string($database)
                    ));
                    $server->select_db($database);

                    return $server;
                } catch (Throwable $exception) {
                    $errors[] = sprintf('%s:%d - %s', $host, $port, $exception->getMessage());
                }
            }
        }

        throw new RuntimeException(
            'Unable to connect to MySQL or create/select the database. '
            . 'Check DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME, and database permissions. '
            . 'Attempts: ' . implode(' | ', $errors)
        );
    }

    private function ensureRepositoryTable(mysqli $db): void
    {
        $db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migrations_migration_unique` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    /** @return array<string, true> */
    private function appliedMigrations(mysqli $db): array
    {
        $result = $db->query('SELECT migration FROM migrations ORDER BY id ASC');
        $applied = [];

        while ($row = $result?->fetch_assoc()) {
            $applied[(string) $row['migration']] = true;
        }

        return $applied;
    }

    private function nextBatchNumber(mysqli $db): int
    {
        $result = $db->query('SELECT COALESCE(MAX(batch), 0) AS batch_no FROM migrations');
        $row = $result ? $result->fetch_assoc() : null;

        return ((int) ($row['batch_no'] ?? 0)) + 1;
    }

    private function recordMigration(mysqli $db, string $migration, int $batch): void
    {
        $stmt = $db->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $stmt->bind_param('si', $migration, $batch);
        $stmt->execute();
    }

    private function loadMigration(string $file): object
    {
        $migration = require $file;

        if (!is_object($migration) || !method_exists($migration, 'up') || !method_exists($migration, 'down')) {
            throw new RuntimeException("Invalid migration file: {$file}");
        }

        return $migration;
    }

    /** @return array<int, string> */
    private function migrationFiles(): array
    {
        $dir = $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        return array_values(array_filter($files, static fn (string $file): bool => is_file($file)));
    }

    private function migrationPathFor(string $migrationName): string
    {
        return $this->basePath
            . DIRECTORY_SEPARATOR
            . 'database'
            . DIRECTORY_SEPARATOR
            . 'migrations'
            . DIRECTORY_SEPARATOR
            . $migrationName;
    }

    private function slugify(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name) ?? $name;

        return trim($name, '_');
    }
}
