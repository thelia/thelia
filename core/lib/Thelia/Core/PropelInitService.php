<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core;

use Propel\Generator\Command\ConfigConvertCommand;
use Propel\Generator\Command\MigrationDiffCommand;
use Propel\Generator\Command\MigrationUpCommand;
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
use Thelia\Core\Propel\Schema\SchemaCombiner;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Log\Tlog;

/**
 * Propel cache and initialization service.
 *
 * @since 2.4
 */
class PropelInitService
{
    /**
     * Name of the Propel initialization file.
     *
     * @var string
     */
    protected static $PROPEL_CONFIG_CACHE_FILENAME = 'propel.init.php';

    /**
     * Application environment.
     *
     * @var string
     */
    protected $environment;

    /**
     * Whether the application is in debug mode.
     *
     * @var bool
     */
    protected $debug;

    /**
     * Map of environment parameters.
     *
     * @var array
     */
    protected $envParameters = [];

    /**
     * Propel schema locator service.
     *
     * @var SchemaLocator
     */
    protected $schemaLocator;

    /**
     * @param string        $environment   application environment
     * @param bool          $debug         whether the application is in debug mode
     * @param array         $envParameters map of environment parameters
     * @param SchemaLocator $schemaLocator propel schema locator service
     */
    public function __construct(
        $environment,
        $debug,
        array $envParameters,
        SchemaLocator $schemaLocator
    ) {
        $this->environment = $environment;
        $this->debug = $debug;
        $this->envParameters = $envParameters;
        $this->schemaLocator = $schemaLocator;
    }

    /**
     * Run a Propel command.
     *
     * @param Command              $command    command to run
     * @param array                $parameters command parameters
     * @param OutputInterface|null $output     command output
     *
     * @throws \Exception
     *
     */
    public function runCommand(Command $command, array $parameters = [], OutputInterface $output = null): int
    {
        $parameters['command'] = $command->getName();
        $input = new ArrayInput($parameters);

        if ($output === null) {
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
            $this->debug
        );

        if ($propelConfigCache->isFresh()) {
            return;
        }

        $configService = new DatabaseConfigurationSource($this->envParameters);

        $propelConfig = $configService->getPropelConnectionsConfiguration();

        $propelConfig['propel']['paths']['phpDir'] = THELIA_ROOT;
        $propelConfig['propel']['generator']['objectModel']['builders'] = [
            'object' => \Thelia\Core\Propel\Generator\Builder\Om\ObjectBuilder::class,
            'objectstub' => \Thelia\Core\Propel\Generator\Builder\Om\ExtensionObjectBuilder::class,
            'objectmultiextend' => \Thelia\Core\Propel\Generator\Builder\Om\MultiExtendObjectBuilder::class,
            'query' => \Thelia\Core\Propel\Generator\Builder\Om\QueryBuilder::class,
            'querystub' => \Thelia\Core\Propel\Generator\Builder\Om\ExtensionQueryBuilder::class,
            'queryinheritance' => \Thelia\Core\Propel\Generator\Builder\Om\QueryInheritanceBuilder::class,
            'queryinheritancestub' => \Thelia\Core\Propel\Generator\Builder\Om\ExtensionQueryInheritanceBuilder::class,
            'tablemap' => \Thelia\Core\Propel\Generator\Builder\Om\TableMapBuilder::class,
            'event' => \Thelia\Core\Propel\Generator\Builder\Om\EventBuilder::class,
        ];

        $propelConfig['propel']['generator']['builders'] = [
            'resolver' => \Thelia\Core\Propel\Generator\Builder\ResolverBuilder::class,
        ];

        $configOptions['propel']['paths']['migrationDir'] = $this->getPropelConfigDir();

        $propelConfigCache->write(
            Yaml::dump($propelConfig)
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
            $this->debug
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
            ]
        );

        // rewrite the file as a cached file
        $propelInitContent = file_get_contents($this->getPropelInitFile());
        $propelInitCache->write(
            $propelInitContent,
            [new FileResource($this->getPropelConfigFile())]
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
            $this->schemaLocator->findForAllModules()
        );

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseSchemaCache = new ConfigCache(
                "{$this->getPropelSchemaDir()}{$database}.schema.xml",
                $this->debug
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
            ]
        );
        $fs->copy(
            $this->getPropelCacheDir().'hash',
            $this->getPropelModelDir().'hash'
        );

        return true;
    }

    public function migrate(): void
    {
        $this->runCommand(
            new MigrationUpCommand(),
            [
                '--config-dir' => $this->getPropelConfigDir(),
                '--output-dir' => THELIA_CACHE_DIR.'propel-migrations'.DS,
            ]
        );

        $this->runCommand(
            new MigrationDiffCommand(),
            [
                '--config-dir' => $this->getPropelConfigDir(),
                '--schema-dir' => $this->getPropelSchemaDir(),
                '--skip-removed-table' => true,
                '--output-dir' => THELIA_CACHE_DIR.'propel-migrations'.DS,
            ]
        );

        $this->runCommand(
            new MigrationUpCommand(),
            [
                '--config-dir' => $this->getPropelConfigDir(),
                '--output-dir' => THELIA_CACHE_DIR.'propel-migrations'.DS,
            ]
        );
    }

    /**
     * Initialize the Propel environment and connection.
     *
     * @param bool $force        force cache generation
     * @param bool $cacheRefresh
     *
     * @throws \Throwable
     *
     * @return bool whether a Propel connection is available
     *
     * @internal
     */
    public function init($force = false, &$cacheRefresh = false)
    {
        $flockFactory = new LockFactory(new FlockStore());

        $lock = $flockFactory->createLock('propel-cache-generation');

        // Acquire a blocking cache generation lock
        $lock->acquire(true);

        try {
            if ($force) {
                (new Filesystem())->remove($this->getPropelCacheDir());
            }

            if (!Thelia::isInstalled()) {
                return false;
            }

            $this->buildPropelConfig();

            $this->buildPropelInitFile();

            $buildPropelGlobalSchema = $this->buildPropelGlobalSchema();
            $buildPropelModels = $this->buildPropelModels();

            require $this->getPropelInitFile();

            if ($buildPropelGlobalSchema || $buildPropelModels) {
                $cacheRefresh = true;
            }

            $theliaDatabaseConnection = Propel::getConnection('TheliaMain');
            $theliaDatabaseConnection->setAttribute(ConnectionWrapper::PROPEL_ATTR_CACHE_PREPARES, true);

            if ($this->debug) {
                Propel::getServiceContainer()->setLogger('defaultLogger', Tlog::getInstance());
                $theliaDatabaseConnection->useDebug(true);
            }
        } catch (\Throwable $th) {
            $fs = new Filesystem();
            $fs->remove(THELIA_CACHE_DIR.$this->environment);
            $fs->remove($this->getPropelModelDir());
            throw $th;
        } finally {
            // Release cache generation lock
            $lock->release();
        }

        return true;
    }


    public function getPropelCacheDir(): string
    {
        return THELIA_CACHE_DIR.$this->environment.DS.'propel'.DS;
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

    public function getPropelDatabaseDir(): string
    {
        return $this->getPropelCacheDir().'database'.DS;
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
