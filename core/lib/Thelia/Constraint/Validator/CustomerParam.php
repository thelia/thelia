<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Constraint\Validator;

use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represent a Customer
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CustomerParam extends IntegerParam
{
    /** @var string Model Class name */
    protected $modelClass = '\Thelia\Model\Customer';

    /** @var ModelCriteria */
    protected $queryBuilder = null;

    /** @var string Customer firstname */
    protected $firstName = null;

    /** @var string Customer lastname */
    protected $lastName = null;

    /** @var string Customer email */
    protected $email = null;

    /**
     * Constructor
     *
     * @param CouponAdapterInterface $adapter Provide necessary value from Thelia
     * @param int                    $integer Integer
     *
     * @throws InvalidArgumentException
     */
    public function __construct(CouponAdapterInterface $adapter, $integer)
    {
        $this->integer = $integer;
        $this->adapter = $adapter;

        $this->queryBuilder = CustomerQuery::create();
        /** @var Customer $customer */
        $customer = $this->queryBuilder->findById($integer);
        if ($customer !== null) {
            $this->firstName = $customer->getFirstname();
            $this->lastName = $customer->getLastname();
            $this->email = $customer->getEmail();
        } else {
            throw new \InvalidArgumentException(
                'CustomerParam can compare only existing Customers'
            );
        }
    }

    /**
     * Compare the current object to the passed $other.
     *
     * Returns 0 if they are semantically equal, 1 if the other object
     * is less than the current one, or -1 if its more than the current one.
     *
     * This method should not check for identity using ===, only for semantically equality for example
     * when two different DateTime instances point to the exact same Date + TZ.
     *
     * @param mixed $other Object
     *
     * @throws InvalidArgumentException
     * @return int
     */
    public function compareTo($other)
    {
        if (!is_integer($other) || $other < 0) {
            throw new InvalidArgumentException(
                'IntegerParam can compare only positive int'
            );
        }

        return parent::compareTo($other);
    }

    /**
     * Customer email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Customer first name
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Customer last name
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip()
    {
        return $this->adapter
            ->getTranslator()
            ->trans(
                'A Customer',
                null,
                'constraint'
            );
    }

}
