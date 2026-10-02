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

namespace Thelia\Tests\Support\Delivery;

use Thelia\Model\Country;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\DeliveryModuleWithStateInterface;

/**
 * A carrier that charges the same thing everywhere, and reads nothing but what it is
 * handed.
 *
 * The two carriers Thelia ships both read the cart back from the session, so neither can
 * answer a quote asked outside a request. This one answers from its arguments alone,
 * which is what lets a test prove that the estimator itself carries the destination all
 * the way down.
 */
final class FixedPostageDeliveryModule extends AbstractDeliveryModule implements DeliveryModuleWithStateInterface
{
    public const MODULE_CODE = 'FixedPostageDeliveryModule';

    public const AMOUNT = 7.5;

    public const AMOUNT_TAX = 1.25;

    public function isValidDelivery(Country $country, ?State $state = null): bool
    {
        return true;
    }

    public function getPostage(Country $country, ?State $state = null): OrderPostage|float
    {
        return new OrderPostage(self::AMOUNT, self::AMOUNT_TAX);
    }

    public function handleVirtualProductDelivery(): bool
    {
        return true;
    }
}
