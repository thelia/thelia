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
use Thelia\Module\BaseModule;

/**
 * Class Payment
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@gmail.com>
 */
class Payment extends BaseSpecificModule
{

    public function getArgDefinitions()
    {
        $collection = parent::getArgDefinitions();

        return $collection;
    }

    public function exec(&$pagination)
    {
        $search = parent::exec($pagination);
        /* manage translations */
        $locale = $this->configureI18nProcessing($search);

        $search->filterByType(BaseModule::PAYMENT_MODULE_TYPE, Criteria::EQUAL);

        /* perform search */
        $paymentModules = $this->search($search, $pagination);

        $loopResult = new LoopResult($paymentModules);

        foreach ($paymentModules as $paymentModule) {
            $loopResultRow = new LoopResultRow($loopResult, $paymentModule, $this->versionable, $this->timestampable, $this->countable);

            $moduleReflection = new \ReflectionClass($paymentModule->getFullNamespace());
            if ($moduleReflection->isSubclassOf("Thelia\Module\PaymentModuleInterface") === false) {
                throw new \RuntimeException(sprintf("payment module %s is not a Thelia\Module\PaymentModuleInterface", $paymentModule->getCode()));
            }
            $moduleInstance = $moduleReflection->newInstance();

            $moduleInstance->setRequest($this->request);
            $moduleInstance->setDispatcher($this->dispatcher);

            $loopResultRow
                ->set('ID', $paymentModule->getId())
                ->set('TITLE', $paymentModule->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $paymentModule->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $paymentModule->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $paymentModule->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
