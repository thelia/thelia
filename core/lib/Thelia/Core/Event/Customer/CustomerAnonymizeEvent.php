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

namespace Thelia\Core\Event\Customer;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Customer;

/**
 * Requests the erasure of the identifying data of a customer, keeping the
 * accounting record of the orders intact.
 *
 * Dispatched with TheliaEvents::CUSTOMER_ANONYMIZE.
 */
class CustomerAnonymizeEvent extends ActionEvent
{
    public function __construct(private readonly Customer $customer)
    {
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }
}
