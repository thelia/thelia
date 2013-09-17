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

namespace Thelia\Core\Template\Loop;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\CountryQuery;
use Thelia\Module\BaseModule;

/**
 * Class Delivery
 * @package Thelia\Core\Template\Loop
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Delivery extends BaseSpecificModule
{

    public function getArgDefinitions()
    {
        $collection = parent::getArgDefinitions();

        $collection->addArgument(
            Argument::createIntTypeArgument("country")
        );

        return $collection;
    }

    public function exec(&$pagination)
    {
        $search = parent::exec($pagination);
        /* manage translations */
        $locale = $this->configureI18nProcessing($search);

        $search->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL);

        $countryId = $this->getCountry();
        if(null !== $countryId) {
            $country = CountryQuery::create()->findPk($countryId);
            if(null === $country) {
                throw new \InvalidArgumentException('Cannot found country id: `' . $countryId . '` in delivery loop');
            }
        } else {
            $country = CountryQuery::create()->findOneByByDefault(1);
        }

        /* perform search */
        $deliveryModules = $this->search($search, $pagination);

        $loopResult = new LoopResult($deliveryModules);

        foreach ($deliveryModules as $deliveryModule) {
            $loopResultRow = new LoopResultRow($loopResult, $deliveryModule, $this->versionable, $this->timestampable, $this->countable);

            $moduleReflection = new \ReflectionClass($deliveryModule->getFullNamespace());
            if ($moduleReflection->isSubclassOf("Thelia\Module\DeliveryModuleInterface") === false) {
                throw new \RuntimeException(sprintf("delivery module %s is not a Thelia\Module\DeliveryModuleInterface", $deliveryModule->getCode()));
            }
            $moduleInstance = $moduleReflection->newInstance();

            $moduleInstance->setRequest($this->request);
            $moduleInstance->setDispatcher($this->dispatcher);

            $loopResultRow
                ->set('ID', $deliveryModule->getId())
                ->set('TITLE', $deliveryModule->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $deliveryModule->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $deliveryModule->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $deliveryModule->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('PRICE', $moduleInstance->calculate($country))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
