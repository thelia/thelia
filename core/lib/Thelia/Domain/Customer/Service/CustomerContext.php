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

namespace Thelia\Domain\Customer\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Customer;

readonly class CustomerContext
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getCustomerFromSession(): ?Customer
    {
        $request = $this->requestStack->getMainRequest();

        return $request?->getSession()->get('customer');
    }
}
