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

namespace Thelia\Domain\Pricing\CustomerPrice;

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * The discount rate of a customer of their own, as the prices apply it: the one rule
 * the price context, the activity checks and the order snapshot share, and the same one
 * the customer groups follow ({@see \Thelia\Model\CustomerGroup::effectiveDiscountRate()}).
 *
 * A rate outside 0..100 (0 excluded), which only a direct write to the database can leave,
 * counts for nothing rather than being capped: it never raises a price nor gives the goods
 * away.
 *
 * A signed-in customer is read back from the database by id rather than from the object
 * the caller holds: the one in the session is serialized when the customer signs in, so an
 * administrator withdrawing the discount would otherwise only take effect at the next
 * sign-in, while the cart, which reads the database, already charges the new price. The
 * answer is kept for the request.
 */
class CustomerDiscountRate implements ResetInterface
{
    /** @var array<int, float> rate by customer id */
    private array $rateByCustomer = [];

    public function rateOf(?Customer $customer): float
    {
        if (null === $customer) {
            return 0.0;
        }

        // Not stored yet (the generated getId() promises an int, yet a new model holds null):
        // the object is all there is to read.
        if ($customer->isNew() || 0 === (int) $customer->getId()) {
            return self::effectiveRate($customer->getDiscount());
        }

        $customerId = (int) $customer->getId();

        return $this->rateByCustomer[$customerId] ??= self::effectiveRate(
            CustomerQuery::create()->filterById($customerId)->select(['Discount'])->findOne(),
        );
    }

    public function reset(): void
    {
        $this->rateByCustomer = [];
    }

    /**
     * The one rule, with no read of the database: for a caller that cannot receive the service
     * (a Propel model) and holds the discount it has just been given, not a copy kept from earlier.
     */
    public static function effectiveRate(mixed $discount): float
    {
        // A DECIMAL column is read as a string, and a customer that is gone as null.
        $rate = (float) $discount;

        return $rate > 0.0 && $rate <= 100.0 ? $rate : 0.0;
    }
}
