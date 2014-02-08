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
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;

/**
 * Class Payment
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@gmail.com>
 */
class Payment extends BaseSpecificModule implements PropelSearchLoopInterface
{

    public function getArgDefinitions()
    {
        $collection = parent::getArgDefinitions();

        return $collection;
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $paymentModule) {
            $loopResultRow = new LoopResultRow($paymentModule);

            $moduleInstance = $this->container->get(sprintf('module.%s', $paymentModule->getCode()));

            if (false === $moduleInstance instanceof PaymentModuleInterface) {
                throw new \RuntimeException(sprintf("payment module %s is not a Thelia\Module\PaymentModuleInterface", $paymentModule->getCode()));
            }

            if (false === $moduleInstance->isValidPayment()) {
                continue;
            }

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

    protected function getModuleType()
    {
        return BaseModule::PAYMENT_MODULE_TYPE;
    }
}
