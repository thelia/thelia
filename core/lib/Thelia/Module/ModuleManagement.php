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
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Thelia\Exception\InvalidModuleException;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 * Class ModuleManagement
 * @package Thelia\Module
 * @author  Manuel Raynaud <manu@thelia.net>
 */
class ModuleManagement
{
    protected $baseModuleDir;
    protected $reflected;

    /** @var ModuleDescriptorValidator $descriptorValidator */
    protected $descriptorValidator;
    protected $errors;

    public function __construct()
    {
        $this->baseModuleDir = THELIA_MODULE_DIR;
    }

    public function updateModules()
    {
        $finder = new Finder();

        $finder
            ->name('module.xml')
            ->in($this->baseModuleDir . '/*/Config');

        $this->errors = [];

        foreach ($finder as $file) {

            try {
                $this->updateModule($file);
            } catch (\Exception $ex) {
                $this->errors[] = $ex;
            }
        }

        if (count($this->errors) > 0) {
            $ex = new InvalidModuleException("");
            $ex->setErrors($this->errors);
            throw $ex;
        }

    }

    /**
     *
     *
     * @param SplFileInfo $file
     *
     * @return Module
     *
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateModule($file)
    {
        $descriptorValidator = $this->getDescriptorValidator();

        $content   = $descriptorValidator->getDescriptor($file->getRealPath());
        $reflected = new \ReflectionClass((string)$content->fullnamespace);
        $code      = basename(dirname($reflected->getFileName()));

        $module = ModuleQuery::create()->filterByCode($code)->findOne();
        if (null === $module) {
            $module = new Module();
            $module->setActivate(0);
        }

        $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $module
                ->setCode($code)
                ->setVersion((string)$content->version)
                ->setFullNamespace((string)$content->fullnamespace)
                ->setType($this->getModuleType($reflected))
                ->setCategory((string)$content->type)
                ->save($con);

            $this->saveDescription($module, $content, $con);

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
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
                ->save($con);
        }

    }
}
