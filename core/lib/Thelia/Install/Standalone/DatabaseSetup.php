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
use Thelia\Module\Exception\InvalidModuleDescriptorException;
use Thelia\Module\Exception\InvalidXmlDocumentException;
use Thelia\Module\ModuleDescriptor;
use Thelia\Module\ModuleDescriptorValidator;
use Thelia\Tools\Version\Version;

final class DatabaseSetup
{
    /**
     * Errors a module update script raises when its change is already in the
     * schema: TheliaMain.sql creates the current shape, then every update/*.sql
     * is replayed over it. A table, column or key it adds already exists (1050,
     * 1060, 1061, 1068, 1826), a column or key it drops is already gone (1091).
     */
    private const IGNORABLE_MYSQL_CODES = [1050, 1060, 1061, 1068, 1091, 1826];

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

    public function getConfig(string $name): ?string
    {
        $statement = $this->pdo->prepare('SELECT `value` FROM `config` WHERE `name` = :name');
        $statement->execute(['name' => $name]);

        $value = $statement->fetchColumn();

        return false === $value ? null : (string) $value;
    }

    public function setConfig(string $name, string $value): void
    {
        $this->pdo->prepare('UPDATE `config` SET `value` = :value WHERE `name` = :name')->execute([
            'value' => $value,
            'name' => $name,
        ]);
    }

    /**
     * Register every module found in the given directories and apply their SQL schema.
     *
     * A module is registered active unless its descriptor declares
     * `<enabled-by-default>0</enabled-by-default>`: such a module ships with the
     * distribution but waits for the merchant to activate it from the back-office, and
     * template:set leaves it alone too (see ModuleManagement). On a database that
     * already knows the module, only the namespace and the version are refreshed: the
     * activation the merchant chose is never rewritten. A mandatory module found inactive
     * once registered is reported in the warnings.
     *
     * Every descriptor is read before anything is written: a refused declaration stops
     * the registration with the module table untouched, whatever order the disk lists
     * the modules in. getWarnings() describes the last registration only.
     *
     * @param string[] $moduleDirectories
     */
    public function registerAndApplyModules(array $moduleDirectories = [THELIA_MODULE_DIR, THELIA_LOCAL_MODULE_DIR]): int
    {
        $this->warnings = [];
        $modules = $this->readModuleDescriptors(array_filter($moduleDirectories, 'is_dir'));

        $insertModule = $this->pdo->prepare(
            'INSERT INTO `module` (`code`, `version`, `type`, `category`, `activate`, `position`, `full_namespace`, `mandatory`, `hidden`, `created_at`)
             VALUES (:code, :version, :type, :category, :activate, :position, :namespace, :mandatory, :hidden, NOW())
             ON DUPLICATE KEY UPDATE `full_namespace` = VALUES(`full_namespace`), `version` = VALUES(`version`)'
        );

        $upsertModuleI18n = $this->pdo->prepare(
            'INSERT INTO `module_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`)
             VALUES (:id, :locale, :title, :description, :chapo, :postscriptum)
             ON DUPLICATE KEY UPDATE `title` = VALUES(`title`)'
        );
        $selectModuleId = $this->pdo->prepare('SELECT `id` FROM `module` WHERE `code` = :code');

        foreach ($modules as $position => $module) {
            $insertModule->execute([...$module['row'], 'position' => $position + 1]);

            $this->insertModuleDescriptions($module['xml'], $module['code'], $upsertModuleI18n, $selectModuleId);
            $this->applyModuleSchema($module['path'], $module['code']);
        }

        // The state written is read back once: on a populated database the row keeps the
        // activation the merchant chose, not the one the descriptor ships.
        $activation = array_map(intval(...), $this->pdo->query('SELECT `code`, `activate` FROM `module`')->fetchAll(\PDO::FETCH_KEY_PAIR));
        $this->warnAboutMandatoryModulesLeftInactive($modules, $activation);
        $this->warnAboutRequiredModulesLeftInactive($modules, $activation);
        // A module found in both module directories is read twice: say it once.
        $this->warnings = array_values(array_unique($this->warnings));

        return \count($modules);
    }

    /**
     * Registering writes each module on its own: an active module whose <required> module
     * ships inactive, or was switched off before this run, is registered active next to an
     * inactive dependency. Activating it from the back-office would have activated the
     * dependency with it; nothing does it here, so the state written is read back and the
     * operator told.
     *
     * @param list<array{code: string, path: string, xml: \SimpleXMLElement, row: array<string, int|string>}> $modules
     * @param array<string, int>                                                                              $activation by module code
     */
    private function warnAboutRequiredModulesLeftInactive(array $modules, array $activation): void
    {
        foreach ($modules as $module) {
            if (1 !== ($activation[$module['code']] ?? null)) {
                continue;
            }

            foreach ($module['xml']->required->module ?? [] as $requiredModule) {
                $requiredCode = trim((string) $requiredModule);

                if (0 === ($activation[$requiredCode] ?? null)) {
                    $this->warn(\sprintf('%s is registered active but requires %s, which is registered inactive: activate %s from the back-office.', $module['code'], $requiredCode, $requiredCode));
                }
            }
        }
    }

    /**
     * <mandatory> only keeps an active module from being deactivated: a mandatory module
     * can be registered inactive, because its descriptor ships it so or because the merchant
     * switched it off before this run. Either way nothing else would say that a module the
     * shop cannot do without is off, so the state is read back after the write and reported.
     *
     * @param list<array{code: string, path: string, xml: \SimpleXMLElement, row: array<string, int|string>}> $modules
     * @param array<string, int>                                                                              $activation by module code
     */
    private function warnAboutMandatoryModulesLeftInactive(array $modules, array $activation): void
    {
        foreach ($modules as $module) {
            if (1 === $module['row']['mandatory'] && 0 === ($activation[$module['code']] ?? null)) {
                $this->warn(\sprintf(ModuleDescriptor::MANDATORY_INACTIVE_WARNING, $module['code']));
            }
        }
    }

    /**
     * A warning names a module by its directory and may quote an SQL error: no control
     * character of either reaches the terminal of the entry point that prints it.
     */
    private function warn(string $warning): void
    {
        $this->warnings[] = InvalidModuleDescriptorException::terminalSafe($warning);
    }

    /**
     * @param string[] $moduleDirs
     *
     * @return list<array{code: string, path: string, xml: \SimpleXMLElement, row: array<string, int|string>}>
     */
    private function readModuleDescriptors(array $moduleDirs): array
    {
        $modules = [];

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

                $row = [
                    'code' => $code,
                    'version' => (string) ($xml->version ?? '0.0.1'),
                    'type' => self::MODULE_TYPE_MAP[$xmlType] ?? 1,
                    'category' => $xmlType,
                    'activate' => $this->enabledByDefault($xml, $moduleXml) ? 1 : 0,
                    'namespace' => (string) ($xml->fullnamespace ?? $code.'\\'.$code),
                    'mandatory' => (int) ($xml->mandatory ?? 0),
                    'hidden' => (int) ($xml->hidden ?? 0),
                ];

                $modules[] = [
                    'code' => $code,
                    'path' => $entry->getPathname(),
                    'xml' => $xml,
                    'row' => $row,
                ];
            }
        }

        return $modules;
    }

    /**
     * The install reads the descriptor without the kernel, so it checks the schema itself
     * when the descriptor carries `<enabled-by-default>`: only the 2.2 format knows the
     * element, as the last one of `<module>`. Every later step (module:refresh, template:set,
     * the activation from the back-office) validates the descriptor before reading it, so a
     * descriptor refused there has to be refused here too, or the module would be registered
     * and impossible to activate.
     */
    private function enabledByDefault(\SimpleXMLElement $xml, string $moduleXml): bool
    {
        // The schema rules first, so that a refused value comes back with the message every
        // later step would give; the reader turns what the schema accepted, or the absence of
        // the element, into a boolean.
        if (0 !== \count($xml->{ModuleDescriptor::ENABLED_BY_DEFAULT})) {
            try {
                (new ModuleDescriptorValidator())->validate($moduleXml);
            } catch (InvalidXmlDocumentException $exception) {
                throw new InvalidModuleDescriptorException(\sprintf('<%s> in %s is refused by the module schema, which accepts it once, as the last element of a 2.2 descriptor, with the value 0 or 1. %s', ModuleDescriptor::ENABLED_BY_DEFAULT, $moduleXml, $exception->getMessage()), 0, $exception);
            }
        }

        return ModuleDescriptor::enabledByDefault($xml, $moduleXml);
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

                    $this->warn("{$moduleName}/".basename($file).": {$e->getMessage()}");
                }
            }
        }
    }
}
