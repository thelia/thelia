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
use Thelia\Core\TheliaKernel;
use Thelia\Tools\Version\Version;

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
        // The collation must match the one the core tables declare: comparing two
        // columns that share a charset but not a collation raises "1267 Illegal mix
        // of collations", and module tables inherit this database default.
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
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
        $this->writeTheliaVersion();
    }

    /**
     * Overwrites the version seeded by insert.sql with the version of the running code.
     *
     * insert.sql is generated from insert.sql.tpl by the generate:sql command, so its version
     * number is a snapshot of the code version at generation time and drifts as soon as a
     * release bumps TheliaKernel::THELIA_VERSION. A fresh install left with an older number
     * announces the wrong version and makes setup/update.php replay every update script above
     * it, on a database that already has the latest schema.
     */
    private function writeTheliaVersion(): void
    {
        $parsedVersion = Version::parse(TheliaKernel::THELIA_VERSION);

        $this->setConfig('thelia_version', $parsedVersion['version']);
        $this->setConfig('thelia_major_version', $parsedVersion['major']);
        $this->setConfig('thelia_minus_version', $parsedVersion['minus']);
        $this->setConfig('thelia_release_version', $parsedVersion['release']);
        $this->setConfig('thelia_extra_version', $parsedVersion['extra']);
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

        $upsertModuleI18n = $this->pdo->prepare(
            'INSERT INTO `module_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`)
             VALUES (:id, :locale, :title, :description, :chapo, :postscriptum)
             ON DUPLICATE KEY UPDATE `title` = VALUES(`title`)'
        );
        $selectModuleId = $this->pdo->prepare('SELECT `id` FROM `module` WHERE `code` = :code');

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

                $this->insertModuleDescriptions($xml, $code, $upsertModuleI18n, $selectModuleId);
                $this->applyModuleSchema($entry->getPathname(), $code);
            }
        }

        return $position;
    }

    /**
     * Persist `<descriptive>` blocks from module.xml into `module_i18n`. Without this step
     * vendor modules registered by registerAndApplyModules() would have no translations,
     * which breaks every consumer that calls Module::getTitle() (e.g. the delivery
     * `DeliveryModuleOption::setTitle()` strict-typed setter, payment module pickers).
     */
    private function insertModuleDescriptions(
        \SimpleXMLElement $xml,
        string $code,
        \PDOStatement $upsert,
        \PDOStatement $selectId,
    ): void {
        $descriptions = $xml->descriptive ?? null;
        if (null === $descriptions || 0 === \count($descriptions)) {
            return;
        }

        $selectId->execute(['code' => $code]);
        $moduleId = $selectId->fetchColumn();
        if (false === $moduleId) {
            return;
        }

        foreach ($descriptions as $desc) {
            $locale = trim((string) ($desc->attributes()->locale ?? ''));
            if ('' === $locale) {
                continue;
            }
            $upsert->execute([
                'id' => (int) $moduleId,
                'locale' => $locale,
                'title' => isset($desc->title) ? (string) $desc->title : $code,
                'description' => isset($desc->description) ? (string) $desc->description : null,
                'chapo' => isset($desc->subtitle) ? (string) $desc->subtitle : null,
                'postscriptum' => isset($desc->postscriptum) ? (string) $desc->postscriptum : null,
            ]);
        }
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
