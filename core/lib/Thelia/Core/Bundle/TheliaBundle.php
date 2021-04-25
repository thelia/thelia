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

namespace Thelia\Core\Bundle;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\PDODataFetcher;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symfony\Component\VarExporter\VarExporter;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Core\DependencyInjection\Compiler\CurrencyConverterProviderPass;
use Thelia\Core\DependencyInjection\Compiler\FallbackParserPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterArchiverPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterAssetFilterPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCommandPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCouponConditionPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCouponPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterFormExtensionPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterFormPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterHookListenersPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterLoopPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterRouterPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Core\DependencyInjection\Compiler\StackPass;
use Thelia\Core\DependencyInjection\Compiler\TranslatorPass;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Core\PropelInitService;
use Thelia\Log\Tlog;
use Thelia\Module\ModuleManagement;
use Thelia\Service\ConfigCacheService;

/**
 * First Bundle use in Thelia
 * It initialize dependency injection container.
 *
 * @TODO load configuration from thelia plugin
 */
class TheliaBundle extends Bundle
{
    public const THELIA_VERSION = '2.5.0';

    /** @var SchemaLocator */
    protected $propelSchemaLocator;

    /** @var PropelInitService */
    protected $propelInitService;

    protected $propelConnectionAvailable;

    protected $theliaDatabaseConnection;

    protected $cacheRefresh = false;

    protected $cacheDir;

    public function boot(): void
    {
        $this->cacheDir = $this->container->getParameter('kernel.cache_dir');

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get('event_dispatcher');

        if ($this->cacheRefresh) {
            $moduleManagement = new ModuleManagement($this->container);
            $moduleManagement->updateModules($this->container);
        }

        if ($this->propelConnectionAvailable) {
            $this->theliaDatabaseConnection->setEventDispatcher($eventDispatcher);
        }

        $this->initializeContainer($this->container);

        if (self::isInstalled()) {
            $eventDispatcher->dispatch((new Event()), TheliaEvents::BOOT);
        }
    }

    protected function initializeContainer(ContainerInterface $container): void
    {
        // initialize Propel, building its cache if necessary
        $this->propelSchemaLocator = new SchemaLocator(
            THELIA_CONF_DIR,
            THELIA_MODULE_DIR
        );

        $this->propelInitService = new PropelInitService(
            $container->getParameter('kernel.environment'),
            $container->getParameter('kernel.debug'),
            $container->getParameter('kernel.cache_dir'),
            $container->getParameterBag()->all(),
            $this->propelSchemaLocator
        );

        $this->propelConnectionAvailable = $this->propelInitService->init(false, $this->cacheRefresh);

        if ($this->propelConnectionAvailable) {
            $this->theliaDatabaseConnection = Propel::getConnection('TheliaMain');
            $this->checkMySQLConfigurations($this->theliaDatabaseConnection);
        }

        (new ConfigCacheService(
            $container->getParameter('kernel.cache_dir')
        ))->initCacheConfigs();

        $container->set('thelia.propel.schema.locator', $this->propelSchemaLocator);
        $container->set('thelia.propel.init', $this->propelInitService);
    }

    private function checkMySQLConfigurations(ConnectionInterface $con): void
    {
        if (!file_exists($this->cacheDir.DS.'check_mysql_configurations.php')) {
            $sessionSqlMode = [];
            $canUpdate = false;
            $logs = [];
            /** @var PDODataFetcher $result */
            $result = $con->query('SELECT VERSION() as version, @@SESSION.sql_mode as session_sql_mode');

            if ($result && $data = $result->fetch(\PDO::FETCH_ASSOC)) {
                $sessionSqlMode = explode(',', $data['session_sql_mode']);
                if (empty($sessionSqlMode[0])) {
                    unset($sessionSqlMode[0]);
                }

                // MariaDB is not impacted by this problem
                if (false === strpos($data['version'], 'MariaDB')) {
                    // MySQL 5.6+ compatibility
                    if (version_compare($data['version'], '5.6.0', '>=')) {
                        // add NO_ENGINE_SUBSTITUTION
                        if (!\in_array('NO_ENGINE_SUBSTITUTION', $sessionSqlMode)) {
                            $sessionSqlMode[] = 'NO_ENGINE_SUBSTITUTION';
                            $canUpdate = true;
                            $logs[] = 'Add sql_mode NO_ENGINE_SUBSTITUTION. Please configure your MySQL server.';
                        }

                        // remove STRICT_TRANS_TABLES
                        if (($key = array_search('STRICT_TRANS_TABLES', $sessionSqlMode)) !== false) {
                            unset($sessionSqlMode[$key]);
                            $canUpdate = true;
                            $logs[] = 'Remove sql_mode STRICT_TRANS_TABLES. Please configure your MySQL server.';
                        }

                        // remove ONLY_FULL_GROUP_BY
                        if (($key = array_search('ONLY_FULL_GROUP_BY', $sessionSqlMode)) !== false) {
                            unset($sessionSqlMode[$key]);
                            $canUpdate = true;
                            $logs[] = 'Remove sql_mode ONLY_FULL_GROUP_BY. Please configure your MySQL server.';
                        }
                    }
                } else {
                    // MariaDB 10.2.4+ compatibility
                    if (version_compare($data['version'], '10.2.4', '>=')) {
                        // remove STRICT_TRANS_TABLES
                        if (($key = array_search('STRICT_TRANS_TABLES', $sessionSqlMode)) !== false) {
                            unset($sessionSqlMode[$key]);
                            $canUpdate = true;
                            $logs[] = 'Remove sql_mode STRICT_TRANS_TABLES. Please configure your MySQL server.';
                        }
                    }

                    if (version_compare($data['version'], '10.1.7', '>=')) {
                        if (!\in_array('NO_ENGINE_SUBSTITUTION', $sessionSqlMode)) {
                            $sessionSqlMode[] = 'NO_ENGINE_SUBSTITUTION';
                            $canUpdate = true;
                            $logs[] = 'Add sql_mode NO_ENGINE_SUBSTITUTION. Please configure your MySQL server.';
                        }
                    }
                }
            } else {
                $logs[] = 'Failed to get MySQL version and sql_mode';
            }

            foreach ($logs as $log) {
                Tlog::getInstance()->addWarning($log);
            }

            (new Filesystem())->dumpFile(
                $this->cacheDir.DS.'check_mysql_configurations.php',
                '<?php return '.VarExporter::export([
                    'modes' => array_values($sessionSqlMode),
                    'canUpdate' => $canUpdate,
                    'logs' => $logs,
                ]).';'
            );
        }

        $cache = require $this->cacheDir.DS.'check_mysql_configurations.php';

        if (!empty($cache['canUpdate'])) {
            if (null === $con->query("SET SESSION sql_mode='".implode(',', $cache['modes'])."';")) {
                throw new \RuntimeException('Failed to set MySQL global and session sql_mode');
            }
        }
    }

    public static function isInstalled()
    {
        return file_exists(THELIA_CONF_DIR.'database.yml');
    }

    /**
     * Construct the depency injection builder.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->cacheDir = $container->getParameter('kernel.cache_dir');

        $this->initializeContainer($container);

        $container
            ->addCompilerPass(new FallbackParserPass())
            ->addCompilerPass(new TranslatorPass())
            ->addCompilerPass(new ControllerArgumentValueResolverPass())
            ->addCompilerPass(new RegisterControllerArgumentLocatorsPass())
            ->addCompilerPass(new RegisterHookListenersPass(), PassConfig::TYPE_AFTER_REMOVING)
            ->addCompilerPass(new RegisterRouterPass())
            ->addCompilerPass(new RegisterCouponPass())
            ->addCompilerPass(new RegisterCouponConditionPass())
            ->addCompilerPass(new RegisterArchiverPass())
            ->addCompilerPass(new RegisterAssetFilterPass())
            ->addCompilerPass(new RegisterSerializerPass())
            ->addCompilerPass(new StackPass())
            ->addCompilerPass(new RegisterFormExtensionPass())
            ->addCompilerPass(new CurrencyConverterProviderPass())
            ->addCompilerPass(new RegisterLoopPass())
            ->addCompilerPass(new RegisterCommandPass())
            ->addCompilerPass(new RegisterFormPass())
        ;
    }
}
