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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\AddressQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * Address loop
 *
 *
 * Class Address
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Address extends BaseLoop
{
    public $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            new Argument(
                'id',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(array('*', 'any'))
                )
            ),
            new Argument(
                'customer',
                new TypeCollection(
                    new Type\IntType(),
                    new Type\EnumType(array('current'))
                ),
                'current'
            ),
            Argument::createBooleanOrBothTypeArgument('default'),
            new Argument(
                'exclude',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(array('none'))
                )
            )
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = AddressQuery::create();

        $id = $this->getId();

        if (null !== $id && !in_array($id, array('*', 'any'))) {
            $search->filterById($id, Criteria::IN);
        }

        $customer = $this->getCustomer();

        if ($customer === 'current') {
            $currentCustomer = $this->securityContext->getCustomerUser();
            if ($currentCustomer === null) {
                return new LoopResult();
            } else {
                $search->filterByCustomerId($currentCustomer->getId(), Criteria::EQUAL);
            }
        } else {
            $search->filterByCustomerId($customer, Criteria::EQUAL);
        }

        $default = $this->getDefault();

        if ($default === true) {
            $search->filterByIsDefault(1, Criteria::EQUAL);
        } else if($default === false) {
            $search->filterByIsDefault(0, Criteria::EQUAL);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude && 'none' !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $addresses = $this->search($search, $pagination);

        $loopResult = new LoopResult($addresses);

        foreach ($addresses as $address) {
            $loopResultRow = new LoopResultRow($loopResult, $address, $this->versionable, $this->timestampable, $this->countable);
            $loopResultRow
                ->set("ID", $address->getId())
                ->set("LABEL", $address->getLabel())
                ->set("CUSTOMER", $address->getCustomerId())
                ->set("TITLE", $address->getTitleId())
                ->set("COMPANY", $address->getCompany())
                ->set("FIRSTNAME", $address->getFirstname())
                ->set("LASTNAME", $address->getLastname())
                ->set("ADDRESS1", $address->getAddress1())
                ->set("ADDRESS2", $address->getAddress2())
                ->set("ADDRESS3", $address->getAddress3())
                ->set("ZIPCODE", $address->getZipcode())
                ->set("CITY", $address->getCity())
                ->set("COUNTRY", $address->getCountryId())
                ->set("PHONE", $address->getPhone())
                ->set("CELLPHONE", $address->getCellphone())
                ->set("DEFAULT", $address->getIsDefault())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
