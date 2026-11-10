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

namespace Thelia\Domain\Subscription\Exception;

/**
 * A plan with no price in the currency asked, nor in the default currency to convert from.
 */
final class SubscriptionPlanPriceMissingException extends SubscriptionException
{
    public function __construct(int $planId, string $currencyCode)
    {
        parent::__construct(\sprintf('The subscription plan %d has no price in %s, nor in the default currency.', $planId, $currencyCode));
    }
}
