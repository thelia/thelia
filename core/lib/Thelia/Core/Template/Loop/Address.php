<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Address as AddressModel;
use Thelia\Model\AddressQuery;
use Thelia\Type\EnumType;
use Thelia\Type\IntListType;
use Thelia\Type\IntType;
use Thelia\Type\TypeCollection;

/**
 * Address loop.
 *
 * Class Address
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method bool|string getDefault()
 * @method string      getCustomer()
 * @method int[]       getExclude()
 */
class Address extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            new Argument(
                'id',
                new TypeCollection(
                    new IntListType(),
                    new EnumType(['*', 'any'])
                )
            ),
            new Argument(
                'customer',
                new TypeCollection(
                    new IntType(),
                    new EnumType(['current'])
                ),
                'current'
            ),
            Argument::createBooleanOrBothTypeArgument('default'),
            new Argument(
                'exclude',
                new TypeCollection(
                    new IntListType(),
                    new EnumType(['none'])
                )
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = AddressQuery::create();

        $id = $this->getId();

        if (null !== $id && !\in_array($id, ['*', 'any'])) {
            $search->filterById($id, Criteria::IN);
        }

        $customer = $this->getCustomer();

        if ($customer === 'current') {
            $currentCustomer = $this->securityContext->getCustomerUser();
            if ($currentCustomer === null) {
                return null;
            }

            $search->filterByCustomerId($currentCustomer->getId(), Criteria::EQUAL);
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

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var AddressModel $address */
        foreach ($loopResult->getResultDataCollection() as $address) {
            $loopResultRow = new LoopResultRow($address);
            $loopResultRow
                ->set('ID', $address->getId())
                ->set('LABEL', $address->getLabel())
                ->set('CUSTOMER', $address->getCustomerId())
                ->set('TITLE', $address->getTitleId())
                ->set('COMPANY', $address->getCompany())
                ->set('FIRSTNAME', $address->getFirstname())
                ->set('LASTNAME', $address->getLastname())
                ->set('ADDRESS1', $address->getAddress1())
                ->set('ADDRESS2', $address->getAddress2())
                ->set('ADDRESS3', $address->getAddress3())
                ->set('ZIPCODE', $address->getZipcode())
                ->set('CITY', $address->getCity())
                ->set('COUNTRY', $address->getCountryId())
                ->set('STATE', $address->getStateId())
                ->set('PHONE', $address->getPhone())
                ->set('CELLPHONE', $address->getCellphone())
                ->set('DEFAULT', $address->getIsDefault())
            ;
            $this->addOutputFields($loopResultRow, $address);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
