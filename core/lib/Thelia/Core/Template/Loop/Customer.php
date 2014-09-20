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
use Thelia\Core\Template\Element\SearchLoopInterface;
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
class Customer extends BaseLoop implements SearchLoopInterface, PropelSearchLoopInterface
{
    protected $timestampable = true;

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
            Argument::createIntTypeArgument('sponsor'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array(
                        'id',
                        'id_reverse',
                        'reference',
                        'reference_reverse',
                        'firstname',
                        'firstname_reverse',
                        'lastname',
                        'lastname_reverse',
                        'last_order',
                        'last_order_reverse',
                        'order_amount',
                        'order_amount_reverse',
                        'registration_date',
                        'registration_date_reverse'
                    ))
                ),
                'lastname'
            )
        );
    }

    public function getSearchIn()
    {
        return array(
            "ref",
            "firstname",
            "lastname",
            "email",
        );
    }

    /**
     * @param CustomerQuery $search
     * @param $searchTerm
     * @param $searchIn
     * @param $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria)
    {
        $search->_and();
        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }
            switch ($searchInElement) {
                case "ref":
                    $search->filterByRef($searchTerm, $searchCriteria);
                    break;
                case "firstname":
                    $search->filterByFirstname($searchTerm, $searchCriteria);
                    break;
                case "lastname":
                    $search->filterByLastname($searchTerm, $searchCriteria);
                    break;
                case "email":
                    $search->filterByEmail($searchTerm, $searchCriteria);
                    break;
            }
        }
    }

    public function buildModelCriteria()
    {
        $search = CustomerQuery::create();

        $current = $this->getCurrent();

        if ($current === true) {
            $currentCustomer = $this->securityContext->getCustomerUser();
            if ($currentCustomer === null) {
                return null;
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

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;

                case 'reference':
                    $search->orderByRef(Criteria::ASC);
                    break;
                case 'reference_reverse':
                    $search->orderByRef(Criteria::DESC);
                    break;

                case 'lastname':
                    $search->orderByLastname(Criteria::ASC);
                    break;
                case 'lastname_reverse':
                    $search->orderByLastname(Criteria::DESC);
                    break;

                case 'firstname':
                    $search->orderByFirstname(Criteria::ASC);
                    break;
                case 'firstname_reverse':
                    $search->orderByFirstname(Criteria::DESC);
                    break;

                case 'registration_date':
                    $search->orderByCreatedAt(Criteria::ASC);
                    break;
                case 'registration_date_reverse':
                    $search->orderByCreatedAt(Criteria::DESC);
                    break;

            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $customer) {
            $loopResultRow = new LoopResultRow($customer);

            $loopResultRow
                ->set("ID", $customer->getId())
                ->set("REF", $customer->getRef())
                ->set("TITLE", $customer->getTitleId())
                ->set("FIRSTNAME", $customer->getFirstname())
                ->set("LASTNAME", $customer->getLastname())
                ->set("EMAIL", $customer->getEmail())
                ->set("RESELLER", $customer->getReseller())
                ->set("SPONSOR", $customer->getSponsor())
                ->set("DISCOUNT", $customer->getDiscount())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
