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

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\InvalidModuleException;
use Thelia\Log\Tlog;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 * Class ModuleManagement.
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class ModuleManagement
{

    protected ?ModuleDescriptorValidator $descriptorValidator = null;

    public function __construct(protected ContainerInterface $container)
    {
    }


    public function updateModules(ContainerInterface $container): void
    {
        $directories = [THELIA_LOCAL_MODULE_DIR, THELIA_MODULE_DIR];
        foreach($directories as $directory) {
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
                    $this->updateModule($file, $container);
                } catch (\Exception $ex) {
                    // Guess module code
                    $moduleCode = basename(\dirname($file, 2));

                    $errors[$moduleCode] = $ex;
                }
            }

            if (\count($errors) > 0) {
                throw new InvalidModuleException($errors);
            }

            if (\count($modulesUpdated)) {
                $this->cacheClear();
            }
        } catch (DirectoryNotFoundException $e) {
            // No module installed
        }
    }

    /**
     * Update module information, and invoke install() for new modules (e.g. modules
     * just discovered), or update() modules for which version number ha changed.
     *
     * @param \SplFileInfo $file      the module.xml file descriptor
     * @param ContainerInterface $container the container
     *
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     *
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
                ->save($con)
            ;

            // Update the module images, title and description when the module is installed, but not after
            // as these data may have been modified byt the administrator
            if ('install' === $action) {
                $this->saveDescription($module, $content, $con);

                if (isset($content->{'images-folder'}) && !$module->isModuleImageDeployed($con)) {
                    /** @var \Thelia\Module\BaseModule $moduleInstance */
                    $moduleInstance = $reflected->newInstance();
                    $imagesFolder = $moduleInstance->getModuleDir().DS.$content->{'images-folder'};
                    $moduleInstance->deployImageFolder($module, $imagesFolder, $con);
                }
            }

            // Tell the module to install() or update()
            $instance = $module->createInstance();

            $instance->setContainer($container);

            if ($action === 'install') {
                $instance->install($con);
            } elseif ($action === 'update') {
                $instance->update($currentVersion, $version, $con);
            }

            if ($action !== 'none') {
                $instance->registerHooks();
            }

            $con->commit();
        } catch (\Exception $ex) {
            Tlog::getInstance()->addError('Failed to update module '.$module->getCode(), $ex);

            $con->rollBack();
            throw $ex;
        }

        return $module;
    }

    public function getDescriptorValidator(): ModuleDescriptorValidator
    {
        if (null === $this->descriptorValidator) {
            $this->descriptorValidator = new ModuleDescriptorValidator();
        }

        return $this->descriptorValidator;
    }

    protected function cacheClear(): void
    {
        $cacheEvent = new CacheEvent(
            $this->container->getParameter('kernel.cache_dir')
        );

        $this->container->get('event_dispatcher')?->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
    }

    private function getModuleType(\ReflectionClass $reflected): int
    {
        if (
            $reflected->implementsInterface('Thelia\Module\DeliveryModuleInterface')
            || $reflected->implementsInterface('Thelia\Module\DeliveryModuleWithStateInterface')
        ) {
            return BaseModule::DELIVERY_MODULE_TYPE;
        }
        if ($reflected->implementsInterface('Thelia\Module\PaymentModuleInterface')) {
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
                ->save($con)
            ;
        }
    }
}
