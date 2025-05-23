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

namespace Thelia\Service\Module;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\DTO\ComposerTheliaModuleDTO;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\Validator\ModuleValidator;
use Thelia\Service\Composer\ComposerHelper;

readonly class ModuleManager
{
    public const COMPOSER_TYPE_MODULE = 'thelia-module';

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ComposerHelper $composerHelper,
        #[Autowire('%kernel.cache_dir%')]
        private string $kernelCacheDir,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function installModule(string $absolutePathToModule): Module
    {
        $moduleValidator = new ModuleValidator($absolutePathToModule);
        $moduleValidator->loadModuleDefinition();
        $checkModule = ModuleQuery::create()->findOneByFullNamespace(
            $moduleValidator->getModuleDefinition()?->getNamespace() ?? ''
        );
        if ($checkModule) {
            return $checkModule;
        }

        $moduleDefinition = $moduleValidator->getModuleDefinition();

        $moduleInstallEvent = new ModuleInstallEvent();
        $moduleInstallEvent
            ->setModulePath($absolutePathToModule)
            ->setModuleDefinition($moduleDefinition);

        $this->eventDispatcher->dispatch($moduleInstallEvent, TheliaEvents::MODULE_INSTALL);

        return $moduleInstallEvent->getModule();
    }

    /**
     * @throws \JsonException
     *
     * @return ComposerTheliaModuleDTO[]
     */
    public function listModulesFromTemplatePath(string $directory): array
    {
        $composerJson = $this->composerHelper->getComposerPackagesFromPath($directory);
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
               || $package['type'] !== self::COMPOSER_TYPE_MODULE) {
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
     * @throws \Exception
     *
     * @return Module[]
     */
    public function installModulesFromTemplatePath(string $path): array
    {
        $modulesInstalled = [];
        if (!file_exists($path.DS.'composer.json')) {
            return [];
        }
        $composerModuleDTOS = $this->listModulesFromTemplatePath($path);
        foreach ($composerModuleDTOS as $composerModuleDTO) {
            $module = $this->installModule($composerModuleDTO->getPath());
            $cacheEvent = new CacheEvent($this->kernelCacheDir);
            $this->eventDispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);

            if ($module->getActivate() === BaseModule::IS_ACTIVATED) {
                continue;
            }
            try {
                $event = new ModuleToggleActivationEvent($module->getId());
                $event->setRecursive(true);

                $this->eventDispatcher->dispatch($event, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
                $modulesInstalled[] = $module;
            } catch (\Exception) {
                continue;
            }
        }

        return $modulesInstalled;
    }
}
