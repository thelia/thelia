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
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symfony\Component\VarExporter\VarExporter;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Controller\ControllerInterface;
use Thelia\Core\Archiver\ArchiverInterface;
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
use Thelia\Core\DependencyInjection\Loader\XmlFileLoader;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Core\PropelInitService;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Template\Element\BaseLoopInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Form\FormInterface;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\ModuleManagement;
use Thelia\Service\ConfigCacheService;
use TheliaSmarty\Template\SmartyParser;

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

        if ($this->container->getParameter('kernel.debug') && $this->container->has(LoggerInterface::class)) {
            // In debug mode, we have to initialize Tlog at this point, as this class uses Propel
            /** @var LoggerInterface $logger */
            $logger = $this->container->get(LoggerInterface::class);
            Propel::getServiceContainer()->setLogger('defaultLogger', $logger);
        }

        if ($this->propelConnectionAvailable) {
            $this->checkMySQLConfigurations($this->theliaDatabaseConnection);
        }

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
                //$logger->warning($log);
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

        $this->loadConfiguration($container);
    }

    /**
     * Load some configuration
     * Initialize all plugins.
     *
     * @throws \Exception
     */
    protected function loadConfiguration(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator($container->getParameter('thelia.core_dir').'/Config/Resources');
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

        $loader = new XmlFileLoader($container, $fileLocator);
        $finder = Finder::create()
            ->name('*.xml')
            ->depth(0)
            ->in($container->getParameter('thelia.core_dir').'/Config/Resources');

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $loader->load($file->getBaseName());
        }

        if (\defined('THELIA_INSTALL_MODE') === false) {
            $modules = ModuleQuery::getActivated();

            $translationDirs = [];

            /** @var Module $module */
            foreach ($modules as $module) {
                //In case modules want add configuration
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

                $envConfigFileName = sprintf('config_%s.xml', $container->getParameter('kernel.debug'));
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
            }

            $parser = $container->getDefinition(SmartyParser::class);

            /** @var \Thelia\Core\Template\TemplateHelperInterface $templateHelper */
            $templateHelper = $container->get('thelia.template_helper');

            /** @var Module $module */
            foreach ($modules as $module) {
                $this->loadModuleTranslationDirectories($module, $translationDirs, $templateHelper);

                $this->addStandardModuleTemplatesToParserEnvironment($parser, $module);
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
            }
        }
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
}
