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
use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\CustomerQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * Customer loop
 *
 *
 * Class Customer
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Customer extends BaseLoop
{
    public $timestampable = true;
    
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createBooleanTypeArgument('current', 1),
            Argument::createIntListTypeArgument('id'),
            new Argument(
                'ref',
                new TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            ),
            Argument::createBooleanTypeArgument('reseller'),
            Argument::createBooleanTypeArgument('last_order'),
            Argument::createIntTypeArgument('sponsor')
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = CustomerQuery::create();

        $current = $this->getCurrent();

        if ($current === true) {
            $currentCustomer = $this->request->getSession()->getCustomerUser();
            if ($currentCustomer === null) {
                return new LoopResult();
            } else {
                $search->filterById($currentCustomer->getId(), Criteria::EQUAL);
            }
        }

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $ref = $this->getRef();

        if (null !== $ref) {
            $search->filterByRef($ref, Criteria::IN);
        }

        $reseller = $this->getReseller();

        if ($reseller === true) {
            $search->filterByReseller(1, Criteria::EQUAL);
        } elseif ($reseller === false) {
            $search->filterByReseller(0, Criteria::EQUAL);
        }

        $sponsor = $this->getSponsor();

        if ($sponsor !== null) {
            $search->filterBySponsor($sponsor, Criteria::EQUAL);
        }

        $customers = $this->search($search, $pagination);

        $loopResult = new LoopResult($customers);

        foreach ($customers as $customer) {
            $loopResultRow = new LoopResultRow($loopResult, $customer, $this->versionable, $this->timestampable, $this->countable);
            $loopResultRow->set("ID", $customer->getId());
            $loopResultRow->set("REF", $customer->getRef());
            $loopResultRow->set("TITLE", $customer->getTitleId());
            $loopResultRow->set("FIRSTNAME", $customer->getFirstname());
            $loopResultRow->set("LASTNAME", $customer->getLastname());
            $loopResultRow->set("EMAIL", $customer->getEmail());
            $loopResultRow->set("RESELLER", $customer->getReseller());
            $loopResultRow->set("SPONSOR", $customer->getSponsor());
            $loopResultRow->set("DISCOUNT", $customer->getDiscount());

            $lastOrderDate = "";
            $lastOrderAmount = "";

            if ($this->getLastOrder()) {
                $order = $customer->getOrders()->getFirst();
                if ($order) {
                    $lastOrderDate = $order->getCreatedAt();
                    $lastOrderAmount = $order->getTotalAmount();
                }
            }

            $loopResultRow->set("LASTORDER_DATE", $lastOrderDate);
            $loopResultRow->set("LASTORDER_AMOUNT", $lastOrderAmount);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
