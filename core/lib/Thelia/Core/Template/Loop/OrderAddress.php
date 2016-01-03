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
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderAddress as OrderAddressModel;

/**
 *
 * OrderAddress loop
 *
 *
 * Class OrderAddress
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int getId()
 */
class OrderAddress extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderAddressQuery::create();

        $id = $this->getId();

        $search->filterById($id, Criteria::IN);

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderAddressModel $orderAddress */
        foreach ($loopResult->getResultDataCollection() as $orderAddress) {
            $loopResultRow = new LoopResultRow($orderAddress);
            $loopResultRow
                ->set("ID", $orderAddress->getId())
                ->set("TITLE", $orderAddress->getCustomerTitleId())
                ->set("COMPANY", $orderAddress->getCompany())
                ->set("FIRSTNAME", $orderAddress->getFirstname())
                ->set("LASTNAME", $orderAddress->getLastname())
                ->set("ADDRESS1", $orderAddress->getAddress1())
                ->set("ADDRESS2", $orderAddress->getAddress2())
                ->set("ADDRESS3", $orderAddress->getAddress3())
                ->set("ZIPCODE", $orderAddress->getZipcode())
                ->set("CITY", $orderAddress->getCity())
                ->set("COUNTRY", $orderAddress->getCountryId())
                ->set("STATE", $orderAddress->getStateId())
                ->set("PHONE", $orderAddress->getPhone())
                ->set("CELLPHONE", $orderAddress->getCellphone())
            ;
            $this->addOutputFields($loopResultRow, $orderAddress);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
