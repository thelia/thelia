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
                $filePath = $file->getRealPath();

                try {
                    $modulesUpdated[] = $this->updateModule($file, $container);
                } catch (\Throwable $ex) {
                    // A module failing on a PHP Error (a stale constant, a missing class) must not
                    // abort the refresh of the other modules.
                    // Guess module code
                    $moduleCode = basename(\dirname($filePath, 2));

                    Tlog::getInstance()->addError('Failed to refresh module '.$moduleCode, $ex);

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
    public function updateModule(\SplFileInfo $file, ContainerInterface $container, bool $forceHookRegistration = false): Module
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

            // $forceHookRegistration covers the module whose files were just replaced without a
            // version bump: the hooks it now declares still have to be registered.
            // createOrUpdateHook() is idempotent, and the positions the administrator set
            // live in module_hook, which this does not touch.
            if ('none' !== $action || $forceHookRegistration) {
                $instance->registerHooks();
            }

            $con->commit();
        } catch (\Throwable $exception) {
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
                ->setTitle(isset($description->title) ? (string) $description->title : null)
                ->setDescription(isset($description->description) ? (string) $description->description : null)
                ->setPostscriptum(isset($description->postscriptum) ? (string) $description->postscriptum : null)
                ->setChapo(isset($description->subtitle) ? (string) $description->subtitle : null)
                ->save($con);
        }
    }

    public function installModule(string $absolutePathToModule): Module
    {
        $moduleValidator = new ModuleValidator($absolutePathToModule);

        return $this->findRegistered($moduleValidator) ?? $this->install($moduleValidator, $absolutePathToModule);
    }

    /**
     * The row the module table already holds for the module the validator describes.
     */
    private function findRegistered(ModuleValidator $moduleValidator): ?Module
    {
        $moduleValidator->loadModuleDefinition();

        return ModuleQuery::create()->findOneByFullNamespace(
            $moduleValidator->getModuleDefinition()?->getNamespace() ?? '',
        );
    }

    /**
     * Install a module the shop does not know yet, and activate it unless its descriptor
     * says it ships inactive. The validator has already loaded and validated the
     * descriptor: every decision below reads it from there instead of parsing module.xml
     * again.
     */
    private function install(ModuleValidator $moduleValidator, string $absolutePathToModule): Module
    {
        $moduleDefinition = $moduleValidator->getModuleDefinition();
        if (null === $moduleDefinition) {
            throw new InvalidModuleException((array) 'Module definition is not valid or not found in ');
        }

        $moduleInstallEvent = new ModuleInstallEvent();
        $moduleInstallEvent
            ->setModulePath($absolutePathToModule)
            ->setModuleDefinition($moduleDefinition);

        $this->eventDispatcher->dispatch($moduleInstallEvent, TheliaEvents::MODULE_INSTALL);

        $module = $moduleInstallEvent->getModule();

        if ($this->shipsInactive($moduleValidator, $absolutePathToModule)) {
            return $module;
        }

        $toggleEvent = new ModuleToggleActivationEvent($module->getId());
        $toggleEvent->setNoCheck(false);
        $toggleEvent->setRecursive(true);
        $this->eventDispatcher->dispatch($toggleEvent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);

        // The activation wrote the row through another instance: read it back so the caller
        // never decides on a stale state.
        $module->reload();

        return $module;
    }

    /**
     * The install step honours `<enabled-by-default>0</enabled-by-default>`: a module a
     * theme requires is installed and registered, but not activated on the merchant's behalf.
     */
    private function shipsInactive(ModuleValidator $moduleValidator, string $absolutePathToModule): bool
    {
        $descriptor = $moduleValidator->getModuleDescriptor();

        // The validator parsed and validated module.xml when it was built, and an invalid
        // file already threw there. Anything but a document here means there is nothing to
        // read, so the historical default, active, applies.
        if (!$descriptor instanceof \SimpleXMLElement) {
            return false;
        }

        return !ModuleDescriptor::enabledByDefault($descriptor, rtrim($absolutePathToModule, DS).DS.'Config'.DS.'module.xml');
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
            $moduleValidator = new ModuleValidator($composerModuleDTO->getPath());
            $registered = $this->findRegistered($moduleValidator);
            $module = $registered ?? $this->install($moduleValidator, $composerModuleDTO->getPath());
            $cacheEvent = new CacheEvent($this->kernelCacheDir);
            $this->eventDispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);

            $modulesInstalled[] = $module;

            if (BaseModule::IS_ACTIVATED === $module->getActivate()) {
                if (null === $registered) {
                    $output?->writeln(\sprintf('<fg=gray>Module %s successfully installed and activated.</>', $module->getCode()));
                }

                continue;
            }

            // Only a module the theme brings is activated on its behalf, above. A module the
            // shop already knows keeps its state: the descriptor asked for it, or the
            // merchant switched it off, and applying a theme is not the moment to overrule
            // either. The install output says which module the theme is missing.
            $output?->writeln(
                \sprintf(
                    $this->shipsInactive($moduleValidator, $composerModuleDTO->getPath())
                        ? '<comment>Module %s is required by the theme but ships inactive: left for the merchant to activate it from the back-office.</comment>'
                        : '<comment>Module %s is required by the theme but was switched off: left as the merchant set it, activate it from the back-office if the theme needs it.</comment>',
                    $module->getCode()
                )
            );
        }

        return $modulesInstalled;
    }
}
