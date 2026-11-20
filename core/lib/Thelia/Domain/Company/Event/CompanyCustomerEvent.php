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

namespace Thelia\Domain\Company\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Model\CompanyCustomer;

/**
 * A customer joined a company, or the limits of a buyer changed, and the change is written.
 */
final class CompanyCustomerEvent extends Event
{
    public function __construct(private readonly CompanyCustomer $buyer)
    {
    }

    public function getBuyer(): CompanyCustomer
    {
        return $this->buyer;
    }
}
