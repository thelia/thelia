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

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Module\Composer\ComposerHelper;
use Thelia\Domain\Module\Composer\DTO\ComposerTheliaModuleDTO;
use Thelia\Domain\Module\Exception\InvalidModuleException;
use Thelia\Log\Tlog;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\Validator\ModuleValidator;

class ModuleManagement
{
    public const COMPOSER_TYPE_MODULE = 'thelia-module';

    protected ?ModuleDescriptorValidator $descriptorValidator = null;

    public function __construct(
        protected ContainerInterface $container,
        protected EventDispatcherInterface $eventDispatcher,
        protected ?ComposerHelper $composerHelper = null,
        #[Autowire(param: 'kernel.cache_dir')]
        protected ?string $kernelCacheDir = null,
    ) {
    }

    public function updateModules(ContainerInterface $container): void
    {
        $directories = [THELIA_LOCAL_MODULE_DIR, THELIA_MODULE_DIR];

        foreach ($directories as $directory) {
            $this->fetchDirModuleForUpdate($directory, $container);
        }
    }

    private function fetchDirModuleForUpdate(string $dir, ContainerInterface $container): void
    {
        try {
            $finder = new Finder();

            $finder
                ->name('module.xml')
                ->in($dir.'*'.DS.'Config');

            $errors = [];

            $modulesUpdated = [];

            foreach ($finder as $file) {
                try {
                    $filePath = $file->getRealPath();
                    $modulesUpdated[] = $this->updateModule($file, $container);
                } catch (\Exception $ex) {
                    // Guess module code
                    $moduleCode = basename(\dirname($filePath, 2));

                    $errors[$moduleCode] = $ex;
                }
            }

            if ([] !== $errors) {
                throw new InvalidModuleException($errors);
            }
        } catch (DirectoryNotFoundException) {
            // No module installed
        }
    }

    /**
     * Update module information, and invoke install() for new modules (e.g. modules
     * just discovered), or update() modules for which version number ha changed.
     *
     * @throws \Exception
     * @throws PropelException
     */
    public function updateModule(\SplFileInfo $file, ContainerInterface $container): Module
    {
        $descriptorValidator = $this->getDescriptorValidator();

        $content = $descriptorValidator->getDescriptor($file->getRealPath());
        $reflected = new \ReflectionClass((string) $content->fullnamespace);
        $code = basename(\dirname($reflected->getFileName()));
        $version = (string) $content->version;
        $currentVersion = $version;
        $mandatory = (int) $content->mandatory;
        $hidden = (int) $content->hidden;

        $module = ModuleQuery::create()->filterByCode($code)->findOne();

        if (null === $module) {
            $module = new Module();
            $module->setActivate(0);

            $action = 'install';
        } elseif ($version !== $module->getVersion()) {
            $currentVersion = $module->getVersion();
            $action = 'update';
        } else {
            $action = 'none';
        }
        $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $module
                ->setCode($code)
                ->setVersion($version)
                ->setFullNamespace((string) $content->fullnamespace)
                ->setType($this->getModuleType($reflected))
                ->setCategory((string) $content->type)
                ->setMandatory($mandatory)
                ->setHidden($hidden)
                ->save($con);

            // Update the module images, title and description when the module is installed, but not after
            // as these data may have been modified byt the administrator
            if ('install' === $action) {
                $this->saveDescription($module, $content, $con);

                if (isset($content->{'images-folder'}) && !$module->isModuleImageDeployed($con)) {
                    /** @var BaseModule $moduleInstance */
                    $moduleInstance = $reflected->newInstance();
                    $imagesFolder = $moduleInstance->getModuleDir().DS.$content->{'images-folder'};
                    $moduleInstance->deployImageFolder($module, $imagesFolder, $con);
                }
            }

            // Tell the module to install() or update()
            $instance = $module->createInstance();

            $instance->setContainer($container);

            if ('install' === $action) {
                $instance->install($con);
            } elseif ('update' === $action) {
                $instance->update($currentVersion, $version, $con);
            }

            if ('none' !== $action) {
                $instance->registerHooks();
            }

            $con->commit();
        } catch (\Exception $exception) {
            Tlog::getInstance()->addError('Failed to update module '.$module->getCode(), $exception);

            $con->rollBack();

            throw $exception;
        }

        return $module;
    }

    public function getDescriptorValidator(): ModuleDescriptorValidator
    {
        if (!$this->descriptorValidator instanceof ModuleDescriptorValidator) {
            $this->descriptorValidator = new ModuleDescriptorValidator();
        }

        return $this->descriptorValidator;
    }

    public function cacheClear(): void
    {
        $cacheEvent = new CacheEvent($this->kernelCacheDir);
        $this->eventDispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
    }

    private function getModuleType(\ReflectionClass $reflected): int
    {
        if (
            $reflected->implementsInterface(DeliveryModuleInterface::class)
            || $reflected->implementsInterface(DeliveryModuleWithStateInterface::class)
        ) {
            return BaseModule::DELIVERY_MODULE_TYPE;
        }

        if ($reflected->implementsInterface(PaymentModuleInterface::class)) {
            return BaseModule::PAYMENT_MODULE_TYPE;
        }

        return BaseModule::CLASSIC_MODULE_TYPE;
    }

    private function saveDescription(Module $module, \SimpleXMLElement $content, ConnectionInterface $con): void
    {
        foreach ($content->descriptive as $description) {
            $locale = (string) $description->attributes()->locale;

            $module
                ->setLocale($locale)
                ->setTitle($description->title)
                ->setDescription($description->description ?? null)
                ->setPostscriptum($description->postscriptum ?? null)
                ->setChapo($description->subtitle ?? null)
                ->save($con);
        }
    }

    public function installModule(string $absolutePathToModule): Module
    {
        $moduleValidator = new ModuleValidator($absolutePathToModule);
        $moduleValidator->loadModuleDefinition();

        $checkModule = ModuleQuery::create()->findOneByFullNamespace(
            $moduleValidator->getModuleDefinition()?->getNamespace() ?? '',
        );
        if ($checkModule) {
            return $checkModule;
        }

        $moduleDefinition = $moduleValidator->getModuleDefinition();
        if (null === $moduleDefinition) {
            throw new InvalidModuleException((array) 'Module definition is not valid or not found in ');
        }

        $moduleInstallEvent = new ModuleInstallEvent();
        $moduleInstallEvent
            ->setModulePath($absolutePathToModule)
            ->setModuleDefinition($moduleDefinition);

        $this->eventDispatcher->dispatch($moduleInstallEvent, TheliaEvents::MODULE_INSTALL);

        $toggleEvent = new ModuleToggleActivationEvent($moduleInstallEvent->getModule()->getId());
        $toggleEvent->setNoCheck(false);
        $toggleEvent->setRecursive(true);
        $this->eventDispatcher->dispatch($toggleEvent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);

        return $moduleInstallEvent->getModule();
    }

    /**
     * @throws \JsonException
     */
    public function listModulesFromTemplatePath(string $directory): array
    {
        $composerJson = $this->composerHelper?->getComposerPackagesFromPath($directory);
        $vendorDir = $composerJson['config']['vendor-dir'] ?? THELIA_ROOT.'vendor';
        $modules = [];

        $installedJsonPath = $vendorDir.'/composer/installed.json';

        if (!file_exists($installedJsonPath)) {
            return $modules;
        }

        $installed = json_decode(file_get_contents($installedJsonPath), true, 512, \JSON_THROW_ON_ERROR);

        $packages = $installed['packages'] ?? $installed;

        foreach ($packages as $package) {
            if (!isset($package['type'], $composerJson['require'][$package['name']])
               || self::COMPOSER_TYPE_MODULE !== $package['type']) {
                continue;
            }

            $installPath = str_replace('..', '', $package['install-path']);
            $packagePath = $vendorDir.$installPath;
            $package['path'] = $packagePath;
            $modules[] = ComposerTheliaModuleDTO::fromArray($package);
        }

        return $modules;
    }

    /**
     * @throws \JsonException
     */
    public function installModulesFromTemplatePath(
        string $path,
        ?OutputInterface $output = null,
    ): array {
        $modulesInstalled = [];

        if (!file_exists($path.DS.'composer.json')) {
            return [];
        }

        $composerModuleDTOS = $this->listModulesFromTemplatePath($path);

        foreach ($composerModuleDTOS as $composerModuleDTO) {
            $output?->writeln(
                \sprintf(
                    '<fg=gray>Installing module %s</>',
                    $composerModuleDTO->getName()
                )
            );
            $module = $this->installModule($composerModuleDTO->getPath());
            $output?->writeln(
                \sprintf(
                    '<fg=gray>Module %s installed.</>',
                    $module->getCode()
                )
            );
            $cacheEvent = new CacheEvent($this->kernelCacheDir);
            $this->eventDispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);

            if (BaseModule::IS_ACTIVATED === $module->getActivate()) {
                $output?->writeln(
                    \sprintf(
                        '<fg=gray>Module %s is already activated.</>',
                        $module->getCode()
                    )
                );
                continue;
            }

            try {
                $event = new ModuleToggleActivationEvent($module->getId());
                $event->setRecursive(true);
                $event->setNoCheck(false);

                $this->eventDispatcher->dispatch($event, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
                $modulesInstalled[] = $module;
                $output?->writeln(
                    \sprintf(
                        '<fg=gray>Module %s successfully installed and activated.</>',
                        $module->getCode()
                    )
                );
            } catch (\Exception $e) {
                Tlog::getInstance()->addError(
                    \sprintf('Failed to activate module %s', $module->getCode()),
                    $e
                );
                $output?->writeln(
                    \sprintf(
                        '<fg=red>Failed to activate module %s: %s %s</>',
                        $module->getCode(),
                        $e->getMessage(),
                        $e->getTraceAsString()
                    )
                );
                continue;
            }
        }

        return $modulesInstalled;
    }
}
