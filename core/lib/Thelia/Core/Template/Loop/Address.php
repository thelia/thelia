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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\AddressQuery;
use Thelia\Model\Address as AddressModel;
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
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method bool|string getDefault()
 * @method string getCustomer()
 * @method int[] getExclude()
 */
class Address extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

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

    public function buildModelCriteria()
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
                return null;
            } else {
                $search->filterByCustomerId($currentCustomer->getId(), Criteria::EQUAL);
            }
        } else {
            $search->filterByCustomerId($customer, Criteria::EQUAL);
        }

        $default = $this->getDefault();

        if ($default === true) {
            $search->filterByIsDefault(1, Criteria::EQUAL);
        } elseif ($default === false) {
            $search->filterByIsDefault(0, Criteria::EQUAL);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude && 'none' !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var AddressModel $address */
        foreach ($loopResult->getResultDataCollection() as $address) {
            $loopResultRow = new LoopResultRow($address);
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
            $this->addOutputFields($loopResultRow, $address);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
