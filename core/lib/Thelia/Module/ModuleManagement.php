<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\Finder\Finder;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleI18n;
use Thelia\Model\ModuleQuery;

/**
 * Class ModuleManagement
 * @package Thelia\Module
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
            if (null === ModuleQuery::create()->filterByCode($code)->findOne()) {
                $module = new Module();
                $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
                $con->beginTransaction();
                try {
                    $module
                        ->setCode($code)
                        ->setFullNamespace((string) $content->fullnamespace)
                        ->setType($this->getModuleType($reflected))
                        ->setActivate(0)
                        ->save($con);

                    $this->saveDescription($module, $content, $con);

                    $con->commit();
                } catch (PropelException $e) {
                    $con->rollBack();
                    throw $e;
                }

            }
        }
    }

    private function saveDescription(Module $module,\SimpleXMLElement $content, ConnectionInterface $con)
    {

        foreach ($content->descriptive as $description) {
            $locale = $description->attributes()->locale;

            $moduleI18n = new ModuleI18n();

            $moduleI18n
                ->setLocale($locale)
                ->setModule($module)
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
