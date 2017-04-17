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

namespace Thelia\Core\Event\Customer;

use Thelia\Model\Customer;
use Thelia\Core\Event\ActionEvent;

class CustomerEvent extends ActionEvent
{
    /** @var null|Customer */
    public $customer = null;

    public function __construct(Customer $customer = null)
    {
        $this->customer = $customer;
    }

    /**
     * @param Customer $customer
     * @return $this
     */
    public function setCustomer(Customer $customer)
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * @return null|Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @return bool
     */
    public function hasCustomer()
    {
        return $this->customer != null;
    }
}
