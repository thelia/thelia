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

namespace Thelia\Core\Propel;

use Propel\Generator\Command\ConfigConvertCommand;
use Propel\Generator\Command\ModelBuildCommand;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Yaml\Yaml;
use Thelia\Config\DatabaseConfigurationSource;
use Thelia\Core\Propel\Generator\Builder\Om\EventBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\ExtensionObjectBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\ExtensionQueryBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\ExtensionQueryInheritanceBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\MultiExtendObjectBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\ObjectBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\QueryBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\QueryInheritanceBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\TableMapBuilder;
use Thelia\Core\Propel\Generator\Builder\ResolverBuilder;
use Thelia\Core\Propel\Schema\SchemaCombiner;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Core\TheliaKernel;
use Thelia\Log\Tlog;

class PropelInitService
{
    protected static string $PROPEL_CONFIG_CACHE_FILENAME = 'propel.init.php';

    public function __construct(
        protected string $environment,
        protected bool $debug,
        protected array $envParameters,
        protected SchemaLocator $schemaLocator,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function runCommand(Command $command, array $parameters = [], ?OutputInterface $output = null): int
    {
        $parameters['command'] = $command->getName();
        $input = new ArrayInput($parameters);

        if (!$output instanceof OutputInterface) {
            $output = new NullOutput();
        }

        $command->setApplication(new SymfonyConsoleApplication());

        return $command->run($input, $output);
    }

    public function buildPropelConfig(): void
    {
        $propelConfigCache = new ConfigCache(
            $this->getPropelConfigFile(),
            $this->debug,
        );

        if ($propelConfigCache->isFresh()) {
            return;
        }

        $configService = new DatabaseConfigurationSource($this->envParameters);

        $propelConfig = $configService->getPropelConnectionsConfiguration();

        $propelConfig['propel']['paths']['phpDir'] = THELIA_ROOT;
        $propelConfig['propel']['generator']['objectModel']['builders'] = [
            'object' => ObjectBuilder::class,
            'objectstub' => ExtensionObjectBuilder::class,
            'objectmultiextend' => MultiExtendObjectBuilder::class,
            'query' => QueryBuilder::class,
            'querystub' => ExtensionQueryBuilder::class,
            'queryinheritance' => QueryInheritanceBuilder::class,
            'queryinheritancestub' => ExtensionQueryInheritanceBuilder::class,
            'tablemap' => TableMapBuilder::class,
            'event' => EventBuilder::class,
        ];

        $propelConfig['propel']['generator']['builders'] = [
            'resolver' => ResolverBuilder::class,
        ];

        $propelConfig['propel']['paths']['migrationDir'] = $this->getPropelConfigDir();

        $propelConfigCache->write(
            Yaml::dump($propelConfig),
        );
    }

    /**
     * @throws \Exception
     */
    public function buildPropelInitFile(): void
    {
        $propelInitCache = new ConfigCache(
            $this->getPropelInitFile(),
            $this->debug,
        );

        if ($propelInitCache->isFresh()) {
            return;
        }

        $this->runCommand(
            new ConfigConvertCommand(),
            [
                '--config-dir' => $this->getPropelConfigDir(),
                '--output-dir' => $this->getPropelConfigDir(),
                '--output-file' => static::$PROPEL_CONFIG_CACHE_FILENAME,
                '--loader-script-dir' => $this->getPropelLoaderScriptDir(),
            ],
        );

        // Rewrite through ConfigCache so Symfony tracks the source propel.yml as a dependency
        // and invalidates the cache when it changes.
        $propelInitContent = file_get_contents($this->getPropelInitFile());
        $propelInitCache->write(
            $propelInitContent,
            [new FileResource($this->getPropelConfigFile())],
        );
    }

    public function buildPropelGlobalSchema(): bool
    {
        $fs = new Filesystem();
        $schemaDir = $this->getPropelSchemaDir();

        if ($fs->exists($schemaDir)) {
            // A populated $schemaDir means a previous build completed (atomic
            // rename below). An empty one is leftover from a build that crashed
            // before writing any file — treat it as incomplete and rebuild.
            if (glob($schemaDir.'*.schema.xml')) {
                return false;
            }
            $fs->remove($schemaDir);
        }

        // Build into a sibling .tmp dir then atomic-rename. If an exception fires
        // mid-build the final $schemaDir never exists, so the next run rebuilds
        // cleanly instead of being short-circuited by an empty leftover dir.
        $tmpDir = rtrim($schemaDir, DS).'.tmp'.DS;
        if ($fs->exists($tmpDir)) {
            $fs->remove($tmpDir);
        }
        $fs->mkdir($tmpDir);

        $activeCodes = $this->getActiveModuleCodes();
        $schemas = $activeCodes === null
            ? $this->schemaLocator->findForAllModules()
            : $this->schemaLocator->findForModules($activeCodes, true);

        $schemaCombiner = new SchemaCombiner($schemas);
        $hash = '';

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseSchemaCache = new ConfigCache(
                \sprintf('%s%s.schema.xml', $tmpDir, $database),
                $this->debug,
            );

            $databaseSchemaCache->write($schemaCombiner->getCombinedDocument($database)->saveXML());

            $hash .= md5(file_get_contents($tmpDir.$database.'.schema.xml'));
        }

        $fs->rename($tmpDir, $schemaDir);

        $fs->dumpFile($this->getPropelCacheDir().'hash', $hash);

        return true;
    }

    /**
     * @throws \Exception
     */
    public function buildPropelModels(): bool
    {
        $fs = new Filesystem();

        if ($fs->exists($this->getPropelModelDir().'hash')
            && file_get_contents($this->getPropelCacheDir().'hash') === file_get_contents($this->getPropelModelDir().'hash')) {
            return false;
        }

        $fs->remove($this->getPropelModelDir());

        $this->runCommand(
            new ModelBuildCommand(),
            [
                '--config-dir' => $this->getPropelConfigDir(),
                '--schema-dir' => $this->getPropelSchemaDir(),
                '--loader-script-dir' => $this->getPropelLoaderScriptDir(),
            ],
        );
        $fs->copy(
            $this->getPropelCacheDir().'hash',
            $this->getPropelModelDir().'hash',
        );

        return true;
    }

    /**
     * @throws \Throwable
     *
     * @internal
     */
    public function init(bool $force = false): bool
    {
        if (!$force && !TheliaKernel::isInstalled()) {
            return false;
        }

        $this->waitForConcurrentBuild();

        // Fast path must not purge the cache on failure: a transient error on one worker
        // would otherwise cascade into a pool-wide outage. Let exceptions bubble.
        if (!$force && $this->isCacheComplete()) {
            $this->loadPropelRuntime();

            return true;
        }

        $lock = (new LockFactory(new FlockStore()))->createLock('propel-cache-generation');
        $lock->acquire(true);

        $buildingFlag = $this->getBuildingFlagFile();

        try {
            if ($force) {
                (new Filesystem())->remove($this->getPropelCacheDir());
            }

            if (!TheliaKernel::isInstalled()) {
                return false;
            }

            if (!$force && $this->isCacheComplete()) {
                $this->loadPropelRuntime();

                return true;
            }

            (new Filesystem())->mkdir(\dirname($buildingFlag));
            @touch($buildingFlag);

            $this->buildPropelConfig();
            $this->buildPropelInitFile();
            $this->buildPropelGlobalSchema();
            $this->buildPropelModels();

            // Drop negative realpath entries and stale opcode cache for the worker that built,
            // so the just-generated files are visible without waiting for TTL expiry.
            clearstatcache(true);
            if (\function_exists('opcache_reset')) {
                @opcache_reset();
            }

            $this->loadPropelRuntime();
        } finally {
            @unlink($buildingFlag);
            $lock->release();
        }

        return true;
    }

    private function waitForConcurrentBuild(int $maxWaitSeconds = 30, int $pollMicroseconds = 50_000): void
    {
        $flag = $this->getBuildingFlagFile();
        if (!is_file($flag)) {
            return;
        }

        $orphanTtl = 120;
        $deadline = microtime(true) + $maxWaitSeconds;

        while (is_file($flag)) {
            $mtime = @filemtime($flag);
            if ($mtime !== false && time() - $mtime > $orphanTtl) {
                // Orphaned flag (crashed build) — ignore it rather than block forever.
                @unlink($flag);

                return;
            }

            if (microtime(true) >= $deadline) {
                return;
            }

            usleep($pollMicroseconds);
            clearstatcache(true, $flag);
        }
    }

    private function getBuildingFlagFile(): string
    {
        return $this->getPropelCacheDir().'.building';
    }

    private function isCacheComplete(): bool
    {
        $hashFile = $this->getPropelCacheDir().'hash';
        $modelHashFile = $this->getPropelModelDir().'hash';

        return file_exists($this->getPropelInitFile())
            && is_dir($this->getPropelSchemaDir())
            && file_exists($hashFile)
            && file_exists($modelHashFile)
            && file_get_contents($hashFile) === file_get_contents($modelHashFile);
    }

    private function loadPropelRuntime(): void
    {
        require_once $this->getPropelInitFile();

        $theliaDatabaseConnection = Propel::getConnection('TheliaMain');
        $theliaDatabaseConnection->setAttribute(ConnectionWrapper::PROPEL_ATTR_CACHE_PREPARES, true);

        if ($this->debug) {
            Propel::getServiceContainer()->setLogger('defaultLogger', Tlog::getInstance());
            $theliaDatabaseConnection->useDebug(true);
        }
    }

    public function getPropelCacheDir(): string
    {
        return THELIA_ROOT.'var'.DS.'propel'.DS.$this->environment.DS;
    }

    public function getPropelConfigDir(): string
    {
        return $this->getPropelCacheDir().'config'.DS;
    }

    public function getPropelConfigFile(): string
    {
        return $this->getPropelConfigDir().'propel.yml';
    }

    public function getPropelInitFile(): string
    {
        return $this->getPropelConfigDir().static::$PROPEL_CONFIG_CACHE_FILENAME;
    }

    public function getPropelSchemaDir(): string
    {
        return $this->getPropelCacheDir().'schema'.DS;
    }

    public function getPropelModelDir(): string
    {
        return $this->getPropelCacheDir().'model'.DS;
    }

    public function getPropelMigrationDir(): string
    {
        return $this->getPropelCacheDir().'migration'.DS;
    }

    public function getPropelLoaderScriptDir(): string
    {
        return $this->getPropelCacheDir().'loader'.DS;
    }

    /**
     * Read the active module codes from the database using a direct PDO connection.
     *
     * Returns null when the database is unreachable, the module table does not
     * exist yet, or any other failure occurs — in that case the caller falls back
     * to scanning the whole filesystem (install-time / recovery behaviour).
     *
     * The Propel configuration file is the source of truth for the DSN so this
     * method is always consistent with the connection Propel will use at runtime.
     *
     * @return string[]|null list of active module codes, or null on failure
     */
    private function getActiveModuleCodes(): ?array
    {
        $configFile = $this->getPropelConfigFile();

        if (!is_file($configFile)) {
            return null;
        }

        try {
            $config = Yaml::parseFile($configFile);
            $connection = $config['propel']['database']['connections']['TheliaMain'] ?? null;

            if (!\is_array($connection) || empty($connection['dsn'])) {
                return null;
            }

            $pdo = new \PDO(
                $connection['dsn'],
                $connection['user'] ?? null,
                $connection['password'] ?? null,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 2],
            );

            $statement = $pdo->query('SELECT `code` FROM `module` WHERE `activate` = 1');

            if ($statement === false) {
                return null;
            }

            return $statement->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            return null;
        }
    }
}
