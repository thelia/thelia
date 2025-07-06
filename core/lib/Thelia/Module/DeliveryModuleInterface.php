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

namespace Thelia\Module;

use Thelia\Model\Country;
use Thelia\Model\OrderPostage;
use Thelia\Module\Exception\DeliveryException;

interface DeliveryModuleInterface extends BaseModuleInterface
{
    /**
     * This method is called by the Delivery  loop, to check if the current module has to be displayed to the customer.
     * Override it to implements your delivery rules/.
     *
     * If you return true, the delivery method will de displayed to the customer
     * If you return false, the delivery method will not be displayed
     *
     * @param Country $country the country to deliver to
     *
     * @return bool
     */
    public function isValidDelivery(Country $country);

    /**
     * Calculate and return delivery price in the shop's default currency.
     *
     * @param Country $country the country to deliver to
     *
     * @throws DeliveryException if the postage price cannot be calculated
     *
     * @return OrderPostage|float the delivery price
     */
    public function getPostage(Country $country);

    /**
     * This method return true if your delivery manages virtual product delivery.
     *
     * @return bool
     */
    public function handleVirtualProductDelivery();
}
