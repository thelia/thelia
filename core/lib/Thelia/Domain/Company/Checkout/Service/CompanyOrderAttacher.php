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

namespace Thelia\Domain\Company\Checkout\Service;

use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Model\Order;

/**
 * Writes on a new order the company it is placed for and the buyer who placed it.
 *
 * Both are frozen with the order: the buyer who changes company, or leaves, leaves their
 * orders where they were. An order of a customer who belongs to no company carries neither,
 * and nothing is read for it beyond the membership lookup every placement makes once.
 */
final readonly class CompanyOrderAttacher
{
    public function __construct(private CompanyMembershipReader $membershipReader)
    {
    }

    public function attach(Order $order, UserInterface $customer): void
    {
        $membership = $this->membershipReader->membershipOf((int) $customer->getId());

        $order
            ->setCompanyId($membership?->companyId)
            ->setPlacedByCustomerId(null === $membership ? null : (int) $customer->getId());
    }
}
