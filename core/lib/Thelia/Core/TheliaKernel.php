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
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\VarExporter\VarExporter;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Api\Bridge\Propel\Extension\QueryCollectionExtensionInterface;
use Thelia\Api\Bridge\Propel\Extension\QueryItemExtensionInterface;
use Thelia\Api\Bridge\Propel\Filter\FilterInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Controller\ControllerInterface;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Bundle\TheliaBundle;
use Thelia\Core\DependencyInjection\Loader\XmlFileLoader;
use Thelia\Core\DependencyInjection\TheliaContainer;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Hook\BaseHookInterface;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\Propel\PropelInitService;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Core\Security\UserProvider\AdminUserProvider;
use Thelia\Core\Security\UserProvider\CustomerUserProvider;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Template\Element\LoopInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxTypeInterface;
use Thelia\Form\FormInterface;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

class TheliaKernel extends Kernel
{
    use MicroKernelTrait;

    public const THELIA_VERSION = '2.5.5';

    protected SchemaLocator $propelSchemaLocator;
    protected PropelInitService $propelInitService;
    protected ParserResolver $parserResolver;
    protected ConnectionInterface $theliaDatabaseConnection;
    protected bool $propelConnectionAvailable;

    public function __construct(string $environment, bool $debug)
    {
        $loader = new ClassLoader();

        $loader->addPsr4('', THELIA_ROOT.\sprintf('var/cache/%s/propel/model', $environment));

        $loader->addPsr4('TheliaMain\\', THELIA_ROOT.\sprintf('var/cache/%s/propel/database/TheliaMain', $environment));
        $loader->register();

        parent::__construct($environment, $debug);

        if ($debug) {
            Debug::enable();
        }
    }

    /**
     * @throws \Exception
     */
    public function boot(): void
    {
        parent::boot();

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        if ($this->propelConnectionAvailable) {
            $this->theliaDatabaseConnection->setEventDispatcher($eventDispatcher);
        }

        $this->addModuleTemplateToParsers();

        if (self::isInstalled()) {
            $eventDispatcher->dispatch(new Event(), TheliaEvents::BOOT);
        }
    }

    public function registerBundles(): iterable
    {
        $contents = [
            TheliaBundle::class => ['all' => true],
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

    /**
     * @throws \Exception
     */
    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        if (!$request instanceof TheliaRequest) {
            $request = TheliaRequest::createFromBase($request);
        }

        if (!$this->booted) {
            $container = $this->container ?? $this->preBoot();

            if ($container->has('http_cache')) {
                return $container->get('http_cache')?->handle($request, $type, $catch);
            }
        }

        $this->boot();

        return parent::handle($request, $type, $catch);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set(
            'thelia_front_template',
            ConfigQuery::read(TemplateDefinition::FRONT_OFFICE_CONFIG_NAME, 'default'),
        );
        $container->parameters()->set(
            'thelia_admin_template',
            ConfigQuery::read(TemplateDefinition::BACK_OFFICE_CONFIG_NAME, 'default'),
        );

        $container->import(__DIR__.'/../Config/Resources/*.php');

        $container->import(__DIR__.'/../Config/Resources/packages/*.php');
        $container->import(__DIR__.'/../Config/Resources/parameters/*.php');
        $container->import(__DIR__.'/../Config/Resources/services/*.php');
        $container->import(__DIR__.'/../Config/Resources/services/core/*.php');
        $container->import(__DIR__.'/../Config/Resources/services/handlers/*.php');
        $container->import(__DIR__.'/../Config/Resources/services/integrations/*.php');
        $container->import(__DIR__.'/../Config/Resources/services/providers/*.php');
        $container->import(__DIR__.'/../Config/Resources/services/utilities/*.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../Config/Resources/routing/*.php');

        $envRouteDir = __DIR__.'/../Config/Resources/routing/'.$this->environment;

        if (is_dir($envRouteDir)) {
            $routes->import($envRouteDir.'/*.php');
        }
    }

    /**
     * @throws \Throwable
     */
    protected function initializeContainer(): void
    {
        // initialize Propel, building its cache if necessary
        $this->propelSchemaLocator = new SchemaLocator(
            THELIA_CONF_DIR,
            THELIA_MODULE_DIR,
            THELIA_LOCAL_MODULE_DIR,
        );

        $this->propelInitService = new PropelInitService(
            $this->getEnvironment(),
            $this->isDebug(),
            $this->getKernelParameters(),
            $this->propelSchemaLocator,
        );

        $this->propelConnectionAvailable = $this->initializePropelService(false);

        if ($this->propelConnectionAvailable) {
            $this->theliaDatabaseConnection = Propel::getConnection('TheliaMain');
            $this->checkMySQLConfigurations($this->theliaDatabaseConnection);
        }

        parent::initializeContainer();

        $this->getContainer()->set('thelia.propel.schema.locator', $this->propelSchemaLocator);
        $this->getContainer()->set('thelia.propel.init', $this->propelInitService);
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
                $sessionSqlMode = explode(',', (string) $data['session_sql_mode']);

                if (empty($sessionSqlMode[0])) {
                    unset($sessionSqlMode[0]);
                }

                // MariaDB is not impacted by this problem
                if (!str_contains((string) $data['version'], 'MariaDB')) {
                    // MySQL 5.6+ compatibility
                    if (version_compare($data['version'], '5.6.0', '>=')) {
                        // add NO_ENGINE_SUBSTITUTION
                        if (!\in_array('NO_ENGINE_SUBSTITUTION', $sessionSqlMode, true)) {
                            $sessionSqlMode[] = 'NO_ENGINE_SUBSTITUTION';
                            $canUpdate = true;
                            $logs[] = 'Add sql_mode NO_ENGINE_SUBSTITUTION. Please configure your MySQL server.';
                        }

                        // remove STRICT_TRANS_TABLES
                        if (($key = array_search('STRICT_TRANS_TABLES', $sessionSqlMode, true)) !== false) {
                            unset($sessionSqlMode[$key]);
                            $canUpdate = true;
                            $logs[] = 'Remove sql_mode STRICT_TRANS_TABLES. Please configure your MySQL server.';
                        }

                        // remove ONLY_FULL_GROUP_BY
                        if (($key = array_search('ONLY_FULL_GROUP_BY', $sessionSqlMode, true)) !== false) {
                            unset($sessionSqlMode[$key]);
                            $canUpdate = true;
                            $logs[] = 'Remove sql_mode ONLY_FULL_GROUP_BY. Please configure your MySQL server.';
                        }
                    }
                } else {
                    // MariaDB 10.2.4+ compatibility
                    // remove STRICT_TRANS_TABLES
                    if (version_compare($data['version'], '10.2.4', '>=') && $key = \in_array('STRICT_TRANS_TABLES', $sessionSqlMode, true)) {
                        unset($sessionSqlMode[$key]);
                        $canUpdate = true;
                        $logs[] = 'Remove sql_mode STRICT_TRANS_TABLES. Please configure your MySQL server.';
                    }

                    if (version_compare($data['version'], '10.1.7', '>=') && !\in_array('NO_ENGINE_SUBSTITUTION', $sessionSqlMode, true)) {
                        $sessionSqlMode[] = 'NO_ENGINE_SUBSTITUTION';
                        $canUpdate = true;
                        $logs[] = 'Add sql_mode NO_ENGINE_SUBSTITUTION. Please configure your MySQL server.';
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
                ]).';',
            );
        }

        $cache = require $this->getCacheDir().DS.'check_mysql_configurations.php';

        if (!empty($cache['canUpdate']) && null === $con->query("SET SESSION sql_mode='".implode(',', $cache['modes'])."';")) {
            throw new \RuntimeException('Failed to set MySQL global and session sql_mode');
        }
    }

    protected function getContainerBaseClass(): string
    {
        return TheliaContainer::class;
    }

    /**
     * @throws \Throwable
     */
    public function initializePropelService(bool $forcePropelCacheGeneration): bool
    {
        return $this->propelInitService->init($forcePropelCacheGeneration);
    }

    public function getCacheDir(): string
    {
        if (\defined('THELIA_ROOT')) {
            return THELIA_CACHE_DIR.$this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if (\defined('THELIA_ROOT')) {
            return THELIA_LOG_DIR;
        }

        return parent::getLogDir();
    }

    /**
     * @throws \Exception
     */
    protected function buildContainer(): ContainerBuilder
    {
        $container = parent::buildContainer();

        $this->loadService($container);

        $this->loadAutoConfigureInterfaces($container);
        $this->loadModulesConfiguration($container);

        $container->set('thelia.propel.schema.locator', $this->propelSchemaLocator);
        $container->set('thelia.propel.init', $this->propelInitService);
        $this->registerTemplateClassLoader($container);

        return $container;
    }

    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        return array_merge($parameters, [
            'kernel.runtime_environment' => $this->environment,
            'thelia.root_dir' => THELIA_ROOT,
            'thelia.core_dir' => \dirname(__DIR__),
            'thelia.module_dir' => THELIA_MODULE_DIR,
            'thelia.local_module_dir' => THELIA_LOCAL_MODULE_DIR,
            'thelia.database_host' => $_SERVER['DATABASE_HOST'] ?? null,
            'thelia.database_port' => $_SERVER['DATABASE_PORT'] ?? null,
            'thelia.database_name' => $_SERVER['DATABASE_NAME'] ?? null,
            'thelia.database_user' => $_SERVER['DATABASE_USER'] ?? null,
            'thelia.database_password' => $_SERVER['DATABASE_PASSWORD'] ?? null,
        ]);
    }

    public static function isInstalled(): bool
    {
        return file_exists(THELIA_CONF_DIR.'database.yml') || (!empty($_SERVER['DATABASE_HOST']));
    }

    private function loadAutoConfigureInterfaces(ContainerBuilder $container): void
    {
        $autoconfiguredInterfaces = [
            SerializerInterface::class => 'thelia.serializer',
            ArchiverInterface::class => 'thelia.archiver',
            FormExtensionInterface::class => 'thelia.forms.extension',
            ContainerAwareInterface::class => 'thelia.command',
            ControllerInterface::class => 'controller.service_arguments',
            TaxTypeInterface::class => 'thelia.taxType',

            QueryCollectionExtensionInterface::class => 'thelia.api.propel.query_extension.collection',
            QueryItemExtensionInterface::class => 'thelia.api.propel.query_extension.item',
            FilterInterface::class => 'thelia.api.propel.filter',
            ResourceAddonInterface::class => 'thelia.api.resource.addon',
        ];

        foreach ($autoconfiguredInterfaces as $interfaceClass => $tag) {
            $container->registerForAutoconfiguration($interfaceClass)
                ->addTag($tag);
        }

        $container->registerForAutoconfiguration(ConditionInterface::class)
            ->setPublic(true)
            ->addTag('thelia.coupon.addCondition');

        $container->registerForAutoconfiguration(CouponInterface::class)
            ->setPublic(true)
            ->addTag('thelia.coupon.addCoupon');

        $container->registerForAutoconfiguration(LoopInterface::class)
            ->setPublic(true)
            ->setShared(false)
            ->addTag('thelia.loop');

        $container->registerForAutoconfiguration(FormInterface::class)
            ->setPublic(true)
            ->setShared(false)
            ->addTag('thelia.form');

        // We set this particular service with public true to have all of his subscribers after removing type (see TheliaBundle.php)
        $container->registerForAutoconfiguration(BaseHookInterface::class)
            ->addTag('hook.event_listener')
            ->setPublic(true);
    }

    /**
     * @throws \Exception
     */
    private function loadService(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator(__DIR__.'/../Config/Resources');
        $phpLoader = new PhpFileLoader($container, $fileLocator);
        $phpLoader->load('services.php');
    }

    /**
     * @throws \Exception
     */
    private function loadModulesConfiguration(ContainerBuilder $container): void
    {
        if (\defined('THELIA_INSTALL_MODE')) {
            return;
        }

        $modules = ModuleQuery::getActivated();

        /** @var Module $module */
        foreach ($modules as $module) {
            try {
                // In case modules want add configuration
                \call_user_func([$module->getFullNamespace(), 'loadConfiguration'], $container);

                $definition = new Definition();
                $definition->setClass($module->getFullNamespace())
                    ->addMethodCall(
                        'setContainer',
                        [new Reference('service_container')],
                    )
                    ->setPublic(true);

                $container->setDefinition(
                    'module.'.$module->getCode(),
                    $definition,
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

                $envConfigFileName = \sprintf('config_%s.xml', $this->environment);
                $envConfigFile = \sprintf('%s%s%s', $module->getAbsoluteConfigPath(), DS, $envConfigFileName);

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
                    \sprintf('Failed to load module %s: %s', $module->getCode(), $e->getMessage()),
                    $e,
                );
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function registerTemplateClassLoader(ContainerBuilder $container): void
    {
        if (\defined('THELIA_INSTALL_MODE')) {
            return;
        }

        $translationDirs = [];

        /** @var TemplateHelperInterface $templateHelper */
        $templateHelper = $container->get('thelia.template_helper');
        $modules = ModuleQuery::getActivated();

        /** @var Module $module */
        foreach ($modules as $module) {
            try {
                $this->loadModuleTranslationDirectories($module, $translationDirs, $templateHelper);
            } catch (\Exception $e) {
                if ($this->debug) {
                    throw $e;
                }

                Tlog::getInstance()->addError(
                    \sprintf('Failed to load module %s: %s', $module->getCode(), $e->getMessage()),
                    $e,
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
                [$templateDefinition],
            );

            /** @var TemplateDefinition $tplDef */
            foreach ($templateList as $tplDef) {
                if (is_dir($dir = $tplDef->getAbsoluteI18nPath())) {
                    $translationDirs[$tplDef->getTranslationDomain()] = $dir;
                }
            }
        }

        if ([] !== $translationDirs) {
            $this->loadTranslation($container, $translationDirs);
        }

        $this->loadDefaultSecurityConfig($container);
    }

    private function loadDefaultSecurityConfig(Container $container): void
    {
        $extensionConfigsReflection = new \ReflectionProperty(ContainerBuilder::class, 'extensionConfigs');
        $extensionConfigs = $extensionConfigsReflection->getValue($container);

        $extensionConfigs['security'][0]['providers'] = array_merge(
            [
                'admin_provider' => [
                    'id' => AdminUserProvider::class,
                ],
                'customer_provider' => [
                    'id' => CustomerUserProvider::class,
                ],
                'all_users' => [
                    'chain' => [
                        'providers' => ['admin_provider', 'customer_provider'],
                    ],
                ],
            ],
            $extensionConfigs['security'][0]['providers'] ?? [],
        );

        $extensionConfigs['security'][0]['firewalls'] = array_merge(
            [
                'frontLogin' => [
                    'pattern' => '^/api/front/login',
                    'stateless' => true,
                    'provider' => 'customer_provider',
                    'json_login' => [
                        'check_path' => '/api/front/login',
                        'success_handler' => 'lexik_jwt_authentication.handler.authentication_success',
                        'failure_handler' => 'lexik_jwt_authentication.handler.authentication_failure',
                    ],
                ],
                'adminLogin' => [
                    'pattern' => '^/api/admin/login',
                    'stateless' => true,
                    'provider' => 'admin_provider',
                    'json_login' => [
                        'check_path' => '/api/admin/login',
                        'success_handler' => 'lexik_jwt_authentication.handler.authentication_success',
                        'failure_handler' => 'lexik_jwt_authentication.handler.authentication_failure',
                    ],
                ],
                'api' => [
                    'pattern' => '^/api',
                    'stateless' => true,
                    'jwt' => [],
                    'provider' => 'all_users',
                ],
            ],
            $extensionConfigs['security'][0]['firewalls'] ?? [],
        );

        $extensionConfigs['security'][0]['access_control'] = array_merge(
            [
                ['path' => '^/api/front/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api/admin/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api/docs', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api/admin', 'roles' => 'ROLE_ADMIN'],
                ['path' => '^/api/front/account', 'roles' => 'ROLE_CUSTOMER'],
            ],
            $extensionConfigs['security'][0]['access_control'] ?? [],
        );

        $extensionConfigsReflection->setValue($container, $extensionConfigs);
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
            } catch (\InvalidArgumentException) {
                // Ignore missing I18n directories
                Tlog::getInstance()->addWarning(\sprintf('loadTranslation: missing %s directory', $dir));
            }
        }
    }

    /**
     * @throws \Throwable
     */
    private function preBoot(): ContainerInterface
    {
        if (!self::isInstalled()) {
            throw new \RuntimeException('Thelia is not installed');
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
            Request::setTrustedProxies(\is_array($trustedProxies) ? $trustedProxies : array_map('trim', explode(',', (string) $trustedProxies)), $container->getParameter('kernel.trusted_headers'));
        }

        return $container;
    }

    private function loadModuleTranslationDirectories(
        Module $module,
        array &$translationDirs,
        TemplateHelperInterface $templateHelper,
    ): void {
        // Core module translation
        if (is_dir($dir = $module->getAbsoluteI18nPath())) {
            $translationDirs[$module->getTranslationDomain()] = $dir;
        }

        // Admin includes translation
        if (is_dir($dir = $module->getAbsoluteAdminIncludesI18nPath())) {
            $translationDirs[$module->getAdminIncludesTranslationDomain()] = $dir;
        }

        $templateTypes = [
            TemplateDefinition::BACK_OFFICE => 'getBackOfficeTemplateTranslationDomain',
            TemplateDefinition::FRONT_OFFICE => 'getFrontOfficeTemplateTranslationDomain',
            TemplateDefinition::PDF => 'getPdfTemplateTranslationDomain',
            TemplateDefinition::EMAIL => 'getEmailTemplateTranslationDomain',
        ];

        foreach ($templateTypes as $type => $translationMethod) {
            $templates = $templateHelper->getList($type, $module->getAbsoluteTemplateBasePath());

            foreach ($templates as $template) {
                $templateName = $template->getName();
                $typeName = ucfirst((string) TemplateDefinition::$standardTemplatesSubdirs[$type]);
                $moduleMethod = 'getAbsolute'.$typeName.'I18nTemplatePath';

                if (!method_exists($moduleMethod, $module::class)) {
                    continue;
                }

                $translationDirs[$module->{$translationMethod}($templateName)] =
                    $module->{$moduleMethod}($templateName);
            }
        }
    }

    private function addModuleTemplateToParsers(): void
    {
        $parserResolver = $this->container->get('thelia.parser.resolver');
        $parsers = $parserResolver->getParsers();

        if (empty($parsers)) {
            return;
        }

        $modules = ModuleQuery::getActivated();

        foreach ($parsers as $parser) {
            if (!\is_object($parser)) {
                continue;
            }

            foreach ($modules as $module) {
                $this->addTemplatesFromModule($parser, $module);
            }
        }
    }

    private function addTemplatesFromModule(ParserInterface $parser, Module $module): void
    {
        $stdTpls = TemplateDefinition::getStandardTemplatesSubdirsIterator();

        foreach ($stdTpls as $templateType => $templateSubdirName) {
            $templateDirectory = $module->getAbsoluteTemplateDirectoryPath($templateSubdirName);

            try {
                $this->addTemplatesFromDirectory($parser, $module, $templateType, $templateDirectory);
            } catch (\UnexpectedValueException) {
                // The directory does not exist, ignore it.
            }
        }
    }

    private function addTemplatesFromDirectory(ParserInterface $parser, Module $module, int $templateType, string $templateDirectory): void
    {
        $code = ucfirst($module->getCode());
        $templateDirBrowser = new \DirectoryIterator($templateDirectory);

        $contents[TheliaBundle::class] = ['all' => true];

        foreach ($templateDirBrowser as $templateDirContent) {
            if ($templateDirContent->isDir() && !$templateDirContent->isDot()) {
                $parser->addTemplateDirectory(
                    $templateType,
                    $templateDirContent->getFilename(),
                    $templateDirContent->getPathName(),
                    $code,
                );
            }
        }
    }
}
