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
use Symfony\Component\Finder\Finder;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 * Class ModuleManagement
 * @package Thelia\Module
 * @author Manuel Raynaud <manu@thelia.net>
 */
class ModuleManagement
{
    protected $baseModuleDir;
    protected $reflected;

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

        $descriptorValidator = new ModuleDescriptorValidator();
        foreach ($finder as $file) {
            $content = $descriptorValidator->getDescriptor($file->getRealPath());
            $reflected = new \ReflectionClass((string) $content->fullnamespace);
            $code = basename(dirname($reflected->getFileName()));

            $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
            $con->beginTransaction();
            try {
                $module = ModuleQuery::create()->filterByCode($code)->findOne();
                if (null === $module) {
                    $module = new Module();

                    $module
                        ->setCode($code)
                        ->setVersion((string) $content->version)
                        ->setVersionMin((string) $content->min)
                        ->setVersionMax((string) $content->max)
                        ->setFullNamespace((string) $content->fullnamespace)
                        ->setType($this->getModuleType($reflected))
                        ->setCategory((string) $content->type)
                        ->setActivate(0)
                        ->save($con);
                }

                $this->saveDescription($module, $content, $con);
                $con->commit();
            } catch (PropelException $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    private function saveDescription(Module $module, \SimpleXMLElement $content, ConnectionInterface $con)
    {
        foreach ($content->descriptive as $description) {
            $locale = (string) $description->attributes()->locale;

            $module
                ->setLocale($locale)
                ->setTitle($description->title)
                ->setDescription(isset($description->description)?$description->description:null)
                ->setPostscriptum(isset($description->postscriptum)?$description->postscriptum:null)
                ->setChapo(isset($description->subtitle)?$description->subtitle:null)
                ->save($con);
        }
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
}
