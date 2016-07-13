<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use SplFileInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Thelia\Exception\InvalidModuleException;
use Thelia\Log\Tlog;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 * Class ModuleManagement
 * @package Thelia\Module
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class ModuleManagement
{
    protected $baseModuleDir;
    protected $reflected;

    /** @var ModuleDescriptorValidator $descriptorValidator */
    protected $descriptorValidator;

    public function __construct()
    {
        $this->baseModuleDir = THELIA_MODULE_DIR;
    }

    public function updateModules(ContainerInterface $container)
    {
        $finder = new Finder();

        $finder
            ->name('module.xml')
            ->in($this->baseModuleDir . '*' . DS . 'Config')
        ;

        $errors = [];

        foreach ($finder as $file) {
            try {
                $this->updateModule($file, $container);
            } catch (\Exception $ex) {
                // Guess module code
                $moduleCode = basename(dirname(dirname($file)));

                $errors[$moduleCode] = $ex;
            }
        }

        if (count($errors) > 0) {
            throw new InvalidModuleException($errors);
        }
    }

    /**
     * Update module information, and invoke install() for new modules (e.g. modules
     * just discovered), or update() modules for which version number ha changed.
     *
     * @param SplFileInfo $file the module.xml file descriptor
     * @param ContainerInterface $container the container
     *
     * @return Module
     *
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateModule($file, ContainerInterface $container)
    {
        $descriptorValidator = $this->getDescriptorValidator();

        $content = $descriptorValidator->getDescriptor($file->getRealPath());
        $reflected = new \ReflectionClass((string)$content->fullnamespace);
        $code      = basename(dirname($reflected->getFileName()));
        $version   = (string)$content->version;
        $mandatory = intval($content->mandatory);
        $hidden    = intval($content->hidden);

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
                ->setFullNamespace((string)$content->fullnamespace)
                ->setType($this->getModuleType($reflected))
                ->setCategory((string)$content->type)
                ->setMandatory($mandatory)
                ->setHidden($hidden)
                ->save($con);

            // Update the module images, title and description when the module is installed, but not after
            // as these data may have been modified byt the administrator
            if ('install' === $action) {
                $this->saveDescription($module, $content, $con);

                if (isset($content->{"images-folder"}) && !$module->isModuleImageDeployed($con)) {
                    /** @var \Thelia\Module\BaseModule $moduleInstance */
                    $moduleInstance = $reflected->newInstance();
                    $imagesFolder = THELIA_MODULE_DIR . $code . DS . (string)$content->{"images-folder"};
                    $moduleInstance->deployImageFolder($module, $imagesFolder, $con);
                }
            }

            // Tell the module to install() or update()
            $instance = $module->createInstance();

            $instance->setContainer($container);

            if ($action == 'install') {
                $instance->install($con);
            } elseif ($action == 'update') {
                $instance->update($currentVersion, $version, $con);
            }

            if ($action !== 'none') {
                $instance->registerHooks();
            }

            $con->commit();
        } catch (\Exception $ex) {
            Tlog::getInstance()->addError("Failed to update module " . $module->getCode(), $ex);

            $con->rollBack();
            throw $ex;
        }

        return $module;
    }

    /**
     * @return \Thelia\Module\ModuleDescriptorValidator
     */
    public function getDescriptorValidator()
    {
        if (null === $this->descriptorValidator) {
            $this->descriptorValidator = new ModuleDescriptorValidator();
        }

        return $this->descriptorValidator;
    }

    private function getModuleType(\ReflectionClass $reflected)
    {
        if ($reflected->implementsInterface('Thelia\Module\DeliveryModuleInterface')) {
            return BaseModule::DELIVERY_MODULE_TYPE;
        } elseif ($reflected->implementsInterface('Thelia\Module\PaymentModuleInterface')) {
            return BaseModule::PAYMENT_MODULE_TYPE;
        } else {
            return BaseModule::CLASSIC_MODULE_TYPE;
        }
    }

    private function saveDescription(Module $module, \SimpleXMLElement $content, ConnectionInterface $con)
    {
        foreach ($content->descriptive as $description) {
            $locale = (string)$description->attributes()->locale;

            $module
                ->setLocale($locale)
                ->setTitle($description->title)
                ->setDescription(isset($description->description) ? $description->description : null)
                ->setPostscriptum(isset($description->postscriptum) ? $description->postscriptum : null)
                ->setChapo(isset($description->subtitle) ? $description->subtitle : null)
                ->save($con)
            ;
        }
    }
}
