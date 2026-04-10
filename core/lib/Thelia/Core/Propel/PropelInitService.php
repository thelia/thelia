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

/**
 * Propel cache and initialization service.
 */
class PropelInitService
{
    /** Name of the Propel initialization file. */
    protected static string $PROPEL_CONFIG_CACHE_FILENAME = 'propel.init.php';

    /**
     * @param string        $environment   application environment
     * @param bool          $debug         whether the application is in debug mode
     * @param array         $envParameters map of environment parameters
     * @param SchemaLocator $schemaLocator propel schema locator service
     */
    public function __construct(
        /**
         * Application environment.
         */
        protected $environment,
        /**
         * Whether the application is in debug mode.
         */
        protected $debug,
        protected array $envParameters,
        protected SchemaLocator $schemaLocator,
    ) {
    }

    /**
     * Run a Propel command.
     *
     * @param Command              $command    command to run
     * @param array                $parameters command parameters
     * @param OutputInterface|null $output     command output
     *
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

    /**
     * Generate the Propel configuration file.
     */
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
     * Generate the Propel initialization file.
     *
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

        // rewrite the file as a cached file
        $propelInitContent = file_get_contents($this->getPropelInitFile());
        $propelInitCache->write(
            $propelInitContent,
            [new FileResource($this->getPropelConfigFile())],
        );
    }

    /**
     * Generate the global Propel schema(s).
     */
    public function buildPropelGlobalSchema(): bool
    {
        $fs = new Filesystem();

        // TODO: caching rules ?
        if ($fs->exists($this->getPropelSchemaDir())) {
            return false;
        }

        $hash = '';

        $fs->mkdir($this->getPropelSchemaDir());

        $schemaCombiner = new SchemaCombiner(
            $this->schemaLocator->findForAllModules(),
        );

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseSchemaCache = new ConfigCache(
                \sprintf('%s%s.schema.xml', $this->getPropelSchemaDir(), $database),
                $this->debug,
            );

            $databaseSchemaCache->write($schemaCombiner->getCombinedDocument($database)->saveXML());

            $hash .= md5(file_get_contents($this->getPropelSchemaDir().$database.'.schema.xml'));
        }

        $fs->dumpFile($this->getPropelCacheDir().'hash', $hash);

        return true;
    }

    /**
     * Generate the base Propel models.
     *
     * @throws \Exception
     */
    public function buildPropelModels(): bool
    {
        $fs = new Filesystem();

        // cache testing
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
     * Initialize the Propel environment and connection.
     *
     * @param bool $force force cache generation
     *
     * @return bool whether a Propel connection is available
     *
     * @throws \Throwable
     *
     * @internal
     */
    public function init(bool $force = false): bool
    {
        if (!$force && !TheliaKernel::isInstalled()) {
            return false;
        }

        // Fast path: if cache is complete, load runtime without acquiring lock
        if (!$force && $this->isCacheComplete()) {
            try {
                $this->loadPropelRuntime();

                return true;
            } catch (\Throwable $throwable) {
                // Cache files exist but are corrupt/inconsistent — fall through to slow path
                (new Filesystem())->remove($this->getPropelCacheDir());
            }
        }

        // Slow path: acquire lock for cache generation
        $lock = (new LockFactory(new FlockStore()))->createLock('propel-cache-generation');
        $lock->acquire(true);

        try {
            if ($force) {
                (new Filesystem())->remove($this->getPropelCacheDir());
            }

            if (!TheliaKernel::isInstalled()) {
                return false;
            }

            // Re-check after lock (another process may have generated the cache)
            if (!$force && $this->isCacheComplete()) {
                $this->loadPropelRuntime();

                return true;
            }

            $this->buildPropelConfig();
            $this->buildPropelInitFile();
            $this->buildPropelGlobalSchema();
            $this->buildPropelModels();
            $this->loadPropelRuntime();
        } catch (\Throwable $throwable) {
            // Only remove the Propel cache dir, not the entire environment cache
            (new Filesystem())->remove($this->getPropelCacheDir());

            throw $throwable;
        } finally {
            $lock->release();
        }

        return true;
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
}
