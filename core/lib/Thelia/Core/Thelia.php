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

/*
 * Root class of Thelia
 *
 * It extends Symfony\Component\HttpKernel\Kernel for changing some features
 *
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */

use Composer\Autoload\ClassLoader;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\PDODataFetcher;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\VarExporter\VarExporter;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Controller\ControllerInterface;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\DependencyInjection\Loader\XmlFileLoader;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Hook\BaseHookInterface;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Template\Element\BaseLoopInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Form\FormInterface;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\ModuleManagement;
use TheliaSmarty\Template\SmartyParser;

class Thelia extends Kernel
{
    use MicroKernelTrait;

    public const THELIA_VERSION = '2.5.0-alpha2';

    /** @var SchemaLocator */
    protected $propelSchemaLocator;

    /** @var PropelInitService */
    protected $propelInitService;

    protected $propelConnectionAvailable;

    protected $theliaDatabaseConnection;

    protected $cacheRefresh;

    public function __construct($environment, $debug)
    {
        $loader = new ClassLoader();

        $loader->addPsr4('', THELIA_ROOT."var/cache/{$environment}/propel/model");
        if (isset($_SERVER['ACTIVE_ADMIN_TEMPLATE'])) {
            $loader->addPsr4('backOffice\\', THELIA_ROOT."templates/backOffice/{$_SERVER['ACTIVE_ADMIN_TEMPLATE']}/components");
        }
        if (isset($_SERVER['ACTIVE_FRONT_TEMPLATE'])) {
            $loader->addPsr4('frontOffice\\', THELIA_ROOT."templates/frontOffice/{$_SERVER['ACTIVE_FRONT_TEMPLATE']}/components");
        }
        $loader->addPsr4('TheliaMain\\', THELIA_ROOT."var/cache/{$environment}/propel/database/TheliaMain");
        $loader->register();

        parent::__construct($environment, $debug);

        if ($debug) {
            Debug::enable();
        }
    }

    /**
     * Configures the container.
     *
     * You can register extensions:
     *
     *     $c->extension('framework', [
     *         'secret' => '%secret%'
     *     ]);
     *
     * Or services:
     *
     *     $c->services()->set('halloween', 'FooBundle\HalloweenProvider');
     *
     * Or parameters:
     *
     *     $c->parameters()->set('halloween', 'lot of fun');
     */
    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('thelia_default_template', 'default');
        $container->import(__DIR__.'/../Config/Resources/*.yaml');
        $container->import(__DIR__.'/../Config/Resources/{packages}/*.yaml');
        $container->import(__DIR__.'/../Config/Resources/{packages}/'.$this->environment.'/*.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }

    public static function isInstalled()
    {
        return file_exists(THELIA_CONF_DIR.'database.yml') || (!empty($_SERVER['DB_HOST']));
    }

    protected function checkMySQLConfigurations(ConnectionInterface $con): void
    {
        if (!file_exists($this->getCacheDir().DS.'check_mysql_configurations.php')) {
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
                $this->getCacheDir().DS.'check_mysql_configurations.php',
                '<?php return '.VarExporter::export([
                    'modes' => array_values($sessionSqlMode),
                    'canUpdate' => $canUpdate,
                    'logs' => $logs,
                ]).';'
            );
        }

        $cache = require $this->getCacheDir().DS.'check_mysql_configurations.php';

        if (!empty($cache['canUpdate'])) {
            if (null === $con->query("SET SESSION sql_mode='".implode(',', $cache['modes'])."';")) {
                throw new \RuntimeException('Failed to set MySQL global and session sql_mode');
            }
        }
    }

    /**
     * Gets the container's base class.
     *
     * All names except Container must be fully qualified.
     */
    protected function getContainerBaseClass(): string
    {
        return '\Thelia\Core\DependencyInjection\TheliaContainer';
    }

    protected function initializeContainer(): void
    {
        // initialize Propel, building its cache if necessary
        $this->propelSchemaLocator = new SchemaLocator(
            THELIA_CONF_DIR,
            THELIA_MODULE_DIR
        );

        $this->propelInitService = new PropelInitService(
            $this->getEnvironment(),
            $this->isDebug(),
            $this->getKernelParameters(),
            $this->propelSchemaLocator
        );

        $this->propelConnectionAvailable = $this->initializePropelService(false, $this->cacheRefresh);

        if ($this->propelConnectionAvailable) {
            $this->theliaDatabaseConnection = Propel::getConnection('TheliaMain');
            $this->checkMySQLConfigurations($this->theliaDatabaseConnection);
        }

        parent::initializeContainer();

        $this->getContainer()->set('thelia.propel.schema.locator', $this->propelSchemaLocator);
        $this->getContainer()->set('thelia.propel.init', $this->propelInitService);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function boot(): void
    {
        parent::boot();

        if ($this->cacheRefresh) {
            $moduleManagement = new ModuleManagement($this->getContainer());
            $moduleManagement->updateModules($this->getContainer());
        }

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        if ($this->propelConnectionAvailable) {
            $this->theliaDatabaseConnection->setEventDispatcher($eventDispatcher);
        }

        if (self::isInstalled()) {
            $eventDispatcher->dispatch((new Event()), TheliaEvents::BOOT);
        }
    }

    public function initCacheConfigs(bool $force = false): void
    {
        if ($force || !file_exists($this->getCacheDir().DS.'thelia_configs.php')) {
            $caches = [];

            $configs = ConfigQuery::create()->find();

            foreach ($configs as $config) {
                $caches[$config->getName()] = $config->getValue();
            }

            (new Filesystem())->dumpFile(
                $this->getCacheDir().DS.'thelia_configs.php',
            '<?php return '.VarExporter::export($caches).';'
            );
        }

        ConfigQuery::initCache(
            require $this->getCacheDir().DS.'thelia_configs.php'
        );
    }

    /**
     * @param $forcePropelCacheGeneration
     * @param $cacheRefresh
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function initializePropelService($forcePropelCacheGeneration, &$cacheRefresh)
    {
        $cacheRefresh = false;

        return $this->propelInitService->init($forcePropelCacheGeneration, $cacheRefresh);
    }

    /**
     * Add all module's standard templates to the parser environment.
     *
     * @param Definition $parser the parser
     * @param Module     $module the Module
     */
    protected function addStandardModuleTemplatesToParserEnvironment($parser, $module): void
    {
        $stdTpls = TemplateDefinition::getStandardTemplatesSubdirsIterator();

        foreach ($stdTpls as $templateType => $templateSubdirName) {
            $this->addModuleTemplateToParserEnvironment($parser, $module, $templateType, $templateSubdirName);
        }
    }

    /**
     * Add a module template directory to the parser environment.
     *
     * @param Definition $parser             the parser
     * @param Module     $module             the Module
     * @param string     $templateType       the template type (one of the TemplateDefinition type constants)
     * @param string     $templateSubdirName the template subdirectory name (one of the TemplateDefinition::XXX_SUBDIR constants)
     */
    protected function addModuleTemplateToParserEnvironment($parser, $module, $templateType, $templateSubdirName): void
    {
        // Get template path
        $templateDirectory = $module->getAbsoluteTemplateDirectoryPath($templateSubdirName);
        try {
            $templateDirBrowser = new \DirectoryIterator($templateDirectory);

            $code = ucfirst($module->getCode());

            /* browse the directory */
            foreach ($templateDirBrowser as $templateDirContent) {
                /* is it a directory which is not . or .. ? */
                if ($templateDirContent->isDir() && !$templateDirContent->isDot()) {
                    $parser->addMethodCall(
                        'addTemplateDirectory',
                        [
                            $templateType,
                            $templateDirContent->getFilename(),
                            $templateDirContent->getPathName(),
                            $code,
                        ]
                    );
                }
            }
        } catch (\UnexpectedValueException $ex) {
            // The directory does not exists, ignore it.
        }
    }

    private function preBoot(): ContainerInterface
    {
        if (!self::isInstalled()) {
            throw new \Exception('Thelia is not installed');
        }
        if ($this->debug) {
            $this->startTime = microtime(true);
        }
        if ($this->debug && !isset($_ENV['SHELL_VERBOSITY']) && !isset($_SERVER['SHELL_VERBOSITY'])) {
            putenv('SHELL_VERBOSITY=3');
            $_ENV['SHELL_VERBOSITY'] = 3;
            $_SERVER['SHELL_VERBOSITY'] = 3;
        }

        $this->initializeBundles();
        $this->initializeContainer();

        $container = $this->container;

        if ($container->hasParameter('kernel.trusted_hosts') && $trustedHosts = $container->getParameter('kernel.trusted_hosts')) {
            Request::setTrustedHosts($trustedHosts);
        }

        if ($container->hasParameter('kernel.trusted_proxies') && $container->hasParameter('kernel.trusted_headers') && $trustedProxies = $container->getParameter('kernel.trusted_proxies')) {
            Request::setTrustedProxies(\is_array($trustedProxies) ? $trustedProxies : array_map('trim', explode(',', $trustedProxies)), $container->getParameter('kernel.trusted_headers'));
        }

        return $container;
    }

    /**
     * @throws \Exception
     */
    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): \Symfony\Component\HttpFoundation\Response
    {
        if (!$this->booted) {
            $container = $this->container ?? $this->preBoot();

            if ($container->has('http_cache')) {
                return $container->get('http_cache')->handle($request, $type, $catch);
            }
        }

        $this->boot();

        return parent::handle($request, $type, $catch);
    }

    /**
     * Load some configuration
     * Initialize all plugins.
     *
     * @throws \Exception
     */
    protected function loadConfiguration(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator(__DIR__.'/../Config/Resources');
        $phpLoader = new PhpFileLoader($container, $fileLocator);
        $phpLoader->load('services.php');

        $autoconfiguredInterfaces = [
            SerializerInterface::class => 'thelia.serializer',
            ArchiverInterface::class => 'thelia.archiver',
            FormExtensionInterface::class => 'thelia.forms.extension',
            BaseLoopInterface::class => 'thelia.loop',
            ContainerAwareInterface::class => 'thelia.command',
            FormInterface::class => 'thelia.form',
            CouponInterface::class => 'thelia.coupon.addCoupon',
            ConditionInterface::class => 'thelia.coupon.addCondition',
            ControllerInterface::class => 'controller.service_arguments',
        ];

        foreach ($autoconfiguredInterfaces as $interfaceClass => $tag) {
            $container->registerForAutoconfiguration($interfaceClass)
                ->addTag($tag);
        }

        // We set this particular service with public true to have all of his subscribers after removing type (see TheliaBundle.php)
        $container->registerForAutoconfiguration(BaseHookInterface::class)
            ->addTag('hook.event_listener')
            ->setPublic(true);

        $loader = new XmlFileLoader($container, $fileLocator);
        $finder = Finder::create()
            ->name('*.xml')
            ->depth(0)
            ->in(__DIR__.'/../Config/Resources');

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $loader->load($file->getBaseName());
        }

        if (\defined('THELIA_INSTALL_MODE') === false) {
            $modules = ModuleQuery::getActivated();

            $translationDirs = [];

            /** @var Module $module */
            foreach ($modules as $module) {
                try {
                    // In case modules want add configuration
                    \call_user_func([$module->getFullNamespace(), 'loadConfiguration'], $container);

                    $definition = new Definition();
                    $definition->setClass($module->getFullNamespace());
                    $definition->addMethodCall('setContainer', [new Reference('service_container')]);
                    $definition->setPublic(true);

                    $container->setDefinition(
                        'module.'.$module->getCode(),
                        $definition
                    );

                    $compilers = \call_user_func([$module->getFullNamespace(), 'getCompilers']);

                    foreach ($compilers as $compiler) {
                        if (\is_array($compiler)) {
                            $container->addCompilerPass($compiler[0], $compiler[1]);
                        } else {
                            $container->addCompilerPass($compiler);
                        }
                    }

                    $loader = new XmlFileLoader($container, new FileLocator($module->getAbsoluteConfigPath()));
                    $loader->load('config.xml', 'module.'.$module->getCode());

                    $envConfigFileName = sprintf('config_%s.xml', $this->environment);
                    $envConfigFile = sprintf('%s%s%s', $module->getAbsoluteConfigPath(), DS, $envConfigFileName);

                    if (is_file($envConfigFile) && is_readable($envConfigFile)) {
                        $loader->load($envConfigFileName, 'module.'.$module->getCode());
                    }

                    $templateBasePath = $module->getAbsoluteTemplateBasePath();
                    if (is_dir($templateBasePath)) {
                        $container->loadFromExtension('twig', [
                            'paths' => [
                                $templateBasePath => $module->getCode().'Module',
                            ],
                        ]);
                    }
                } catch (\Exception $e) {
                    if ($this->debug) {
                        throw $e;
                    }
                    Tlog::getInstance()->addError(
                        sprintf('Failed to load module %s: %s', $module->getCode(), $e->getMessage()),
                        $e
                    );
                }
            }

            $parser = $container->getDefinition(SmartyParser::class);

            /** @var \Thelia\Core\Template\TemplateHelperInterface $templateHelper */
            $templateHelper = $container->get('thelia.template_helper');

            /** @var Module $module */
            foreach ($modules as $module) {
                try {
                    $this->loadModuleTranslationDirectories($module, $translationDirs, $templateHelper);

                    $this->addStandardModuleTemplatesToParserEnvironment($parser, $module);
                } catch (\Exception $e) {
                    if ($this->debug) {
                        throw $e;
                    }
                    Tlog::getInstance()->addError(
                        sprintf('Failed to load module %s: %s', $module->getCode(), $e->getMessage()),
                        $e
                    );
                }
            }

            // Load core translation
            $translationDirs['core'] = THELIA_LIB.'Config'.DS.'I18n';

            // Load core translation
            $translationDirs[Translator::GLOBAL_FALLBACK_DOMAIN] = THELIA_LOCAL_DIR.'I18n';

            // Standard templates (front, back, pdf, mail)
            /** @var TemplateDefinition $templateDefinition */
            foreach ($templateHelper->getStandardTemplateDefinitions() as $templateDefinition) {
                // Load parent templates transaltions, the current template translations.
                $templateList = array_merge(
                    $templateDefinition->getParentList(),
                    [$templateDefinition]
                );

                /** @var TemplateDefinition $tplDef */
                foreach ($templateList as $tplDef) {
                    if (is_dir($dir = $tplDef->getAbsoluteI18nPath())) {
                        $translationDirs[$tplDef->getTranslationDomain()] = $dir;
                    }
                }
            }

            if ($translationDirs) {
                $this->loadTranslation($container, $translationDirs);
            }
        }
    }

    /**
     * @param TemplateHelperInterface $templateHelper
     */
    private function loadModuleTranslationDirectories(Module $module, array &$translationDirs, $templateHelper): void
    {
        // Core module translation
        if (is_dir($dir = $module->getAbsoluteI18nPath())) {
            $translationDirs[$module->getTranslationDomain()] = $dir;
        }

        // Admin includes translation
        if (is_dir($dir = $module->getAbsoluteAdminIncludesI18nPath())) {
            $translationDirs[$module->getAdminIncludesTranslationDomain()] = $dir;
        }

        // Module back-office template, if any
        $templates =
            $templateHelper->getList(
                TemplateDefinition::BACK_OFFICE,
                $module->getAbsoluteTemplateBasePath()
            );

        foreach ($templates as $template) {
            $translationDirs[$module->getBackOfficeTemplateTranslationDomain($template->getName())] =
                $module->getAbsoluteBackOfficeI18nTemplatePath($template->getName());
        }

        // Module front-office template, if any
        $templates =
            $templateHelper->getList(
                TemplateDefinition::FRONT_OFFICE,
                $module->getAbsoluteTemplateBasePath()
            );

        foreach ($templates as $template) {
            $translationDirs[$module->getFrontOfficeTemplateTranslationDomain($template->getName())] =
                $module->getAbsoluteFrontOfficeI18nTemplatePath($template->getName());
        }

        // Module pdf template, if any
        $templates =
            $templateHelper->getList(
                TemplateDefinition::PDF,
                $module->getAbsoluteTemplateBasePath()
            );

        foreach ($templates as $template) {
            $translationDirs[$module->getPdfTemplateTranslationDomain($template->getName())] =
                $module->getAbsolutePdfI18nTemplatePath($template->getName());
        }

        // Module email template, if any
        $templates =
            $templateHelper->getList(
                TemplateDefinition::EMAIL,
                $module->getAbsoluteTemplateBasePath()
            );

        foreach ($templates as $template) {
            $translationDirs[$module->getEmailTemplateTranslationDomain($template->getName())] =
                $module->getAbsoluteEmailI18nTemplatePath($template->getName());
        }
    }

    private function loadTranslation(ContainerBuilder $container, array $dirs): void
    {
        $translator = $container->getDefinition(Translator::class);

        foreach ($dirs as $domain => $dir) {
            try {
                $finder = Finder::create()
                    ->files()
                    ->depth(0)
                    ->in($dir);

                /** @var \DirectoryIterator $file */
                foreach ($finder as $file) {
                    [$locale, $format] = explode('.', $file->getBaseName(), 2);

                    $translator->addMethodCall('addResource', [$format, (string) $file, $locale, $domain]);
                }
            } catch (\InvalidArgumentException $ex) {
                // Ignore missing I18n directories
                Tlog::getInstance()->addWarning("loadTranslation: missing $dir directory");
            }
        }
    }

    /**
     * Builds the service container.
     *
     * @return ContainerBuilder The compiled service container
     *
     * @throws \Exception
     */
    protected function buildContainer(): ContainerBuilder
    {
        $container = parent::buildContainer();

        $this->loadConfiguration($container);

        return $container;
    }

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     *
     * @api
     */
    public function getCacheDir(): string
    {
        if (\defined('THELIA_ROOT')) {
            return THELIA_CACHE_DIR.$this->environment;
        }

        return parent::getCacheDir();
    }

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     *
     * @api
     */
    public function getLogDir(): string
    {
        if (\defined('THELIA_ROOT')) {
            return THELIA_LOG_DIR;
        }

        return parent::getLogDir();
    }

    /**
     * Returns the kernel parameters.
     *
     * @return array An array of kernel parameters
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        // Todo replace this by real runtime env
        $parameters['kernel.runtime_environment'] = $this->environment;

        $parameters['thelia.root_dir'] = THELIA_ROOT;
        $parameters['thelia.core_dir'] = \dirname(__DIR__); // This class is in core/lib/Thelia/Core.
        $parameters['thelia.module_dir'] = THELIA_MODULE_DIR;

        $parameters['thelia.db_host'] = $_SERVER['DB_HOST'] ?? null;
        $parameters['thelia.db_port'] = $_SERVER['DB_PORT'] ?? null;
        $parameters['thelia.db_name'] = $_SERVER['DB_NAME'] ?? null;
        $parameters['thelia.db_user'] = $_SERVER['DB_USER'] ?? null;
        $parameters['thelia.db_password'] = $_SERVER['DB_PASSWORD'] ?? null;

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function registerBundles(): iterable
    {
        $contents = [
            Bundle\TheliaBundle::class => ['all' => true],
        ];

        if (file_exists(THELIA_ROOT.'config/bundles.php')) {
            $contents = array_merge($contents, require THELIA_ROOT.'config/bundles.php');
        }

        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }
}
