<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Install\Standalone;

use Thelia\Core\Install\Database;

final class DatabaseSetup
{
    private const IGNORABLE_MYSQL_CODES = [1050, 1060, 1061, 1068, 1826];

    private const MODULE_TYPE_MAP = [
        'classic' => 1,
        'payment' => 3,
        'delivery' => 2,
    ];

    private \PDO $pdo;

    /** @var string[] */
    private array $warnings = [];

    public function __construct(
        private readonly string $host,
        private readonly string $port,
        private readonly string $dbName,
        private readonly string $user,
        private readonly string $password,
    ) {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $this->dbName)) {
            throw new \InvalidArgumentException(\sprintf('Invalid database name: "%s"', $this->dbName));
        }
    }

    public function createDatabase(): void
    {
        $pdo = new \PDO("mysql:host={$this->host};port={$this->port}", $this->user, $this->password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function connect(): void
    {
        $this->pdo = new \PDO(
            "mysql:host={$this->host};dbname={$this->dbName};port={$this->port}",
            $this->user,
            $this->password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
    }

    public function applyCoreSchemaAndSeed(): void
    {
        $database = new Database($this->pdo);
        $database->insertSql(null, [THELIA_SETUP_DIRECTORY.'thelia.sql']);
        $database->insertSql(null, [THELIA_SETUP_DIRECTORY.'insert.sql']);
    }

    public function generateFormSecret(): void
    {
        $secret = \Thelia\Tools\TokenProvider::generateToken();
        $this->pdo->prepare("UPDATE `config` SET `value` = ? WHERE `name` = 'form.secret'")->execute([$secret]);
    }

    public function setConfig(string $name, string $value): void
    {
        $this->pdo->prepare('UPDATE `config` SET `value` = :value WHERE `name` = :name')->execute([
            'value' => $value,
            'name' => $name,
        ]);
    }

    public function registerAndApplyModules(): int
    {
        $moduleDirs = array_filter([THELIA_MODULE_DIR, THELIA_LOCAL_MODULE_DIR], 'is_dir');
        $position = 0;

        $insertModule = $this->pdo->prepare(
            'INSERT INTO `module` (`code`, `version`, `type`, `category`, `activate`, `position`, `full_namespace`, `mandatory`, `hidden`, `created_at`)
             VALUES (:code, :version, :type, :category, 1, :position, :namespace, :mandatory, :hidden, NOW())
             ON DUPLICATE KEY UPDATE `full_namespace` = VALUES(`full_namespace`), `version` = VALUES(`version`)'
        );

        foreach ($moduleDirs as $baseDir) {
            foreach (new \DirectoryIterator($baseDir) as $entry) {
                if (!$entry->isDir() || $entry->isDot()) {
                    continue;
                }

                $moduleXml = $entry->getPathname().'/Config/module.xml';
                if (!file_exists($moduleXml)) {
                    continue;
                }

                $xml = @simplexml_load_file($moduleXml);
                if (false === $xml) {
                    continue;
                }

                $code = $entry->getFilename();
                $xmlType = (string) ($xml->type ?? 'classic');

                $insertModule->execute([
                    'code' => $code,
                    'version' => (string) ($xml->version ?? '0.0.1'),
                    'type' => self::MODULE_TYPE_MAP[$xmlType] ?? 1,
                    'category' => $xmlType,
                    'position' => ++$position,
                    'namespace' => (string) ($xml->fullnamespace ?? $code.'\\'.$code),
                    'mandatory' => (int) ($xml->mandatory ?? 0),
                    'hidden' => (int) ($xml->hidden ?? 0),
                ]);

                $this->applyModuleSchema($entry->getPathname(), $code);
            }
        }

        return $position;
    }

    /** @return string[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    private function applyModuleSchema(string $modulePath, string $moduleName): void
    {
        $mainSql = $modulePath.'/Config/TheliaMain.sql';
        if (!file_exists($mainSql)) {
            return;
        }

        $files = [$mainSql];

        $updateDir = $modulePath.'/Config/update';
        if (is_dir($updateDir)) {
            $updates = glob($updateDir.'/*.sql') ?: [];
            usort($updates, static fn (string $a, string $b) => version_compare(basename($a, '.sql'), basename($b, '.sql')));
            $files = array_merge($files, $updates);
        }

        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if (false === $sql) {
                continue;
            }

            foreach (array_filter(explode(";\n", $sql)) as $statement) {
                $statement = trim($statement);
                if ('' === $statement) {
                    continue;
                }

                try {
                    $this->pdo->exec($statement);
                } catch (\PDOException $e) {
                    $code = (int) ($e->errorInfo[1] ?? 0);

                    if (\in_array($code, self::IGNORABLE_MYSQL_CODES, true)) {
                        continue;
                    }
                    if (1005 === $code && str_contains($e->getMessage(), 'errno: 121')) {
                        continue;
                    }

                    $this->warnings[] = "{$moduleName}/".basename($file).": {$e->getMessage()}";
                }
            }
        }
    }
}
