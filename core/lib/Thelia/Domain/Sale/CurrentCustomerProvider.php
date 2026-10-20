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

namespace Thelia\Domain\Sale;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;

/**
 * The customer the reserved prices of the current request are resolved for.
 *
 * The token storage comes first: it holds who a caller reaching /api/front/...
 * over HTTP with a bearer token authenticated as. That request may also carry a
 * stale front-office session cookie for someone else, and the price must follow
 * the identity the request authenticated as, not the one the session remembers.
 *
 * Thelia's own SecurityContext is the fallback: the front-office theme calls the
 * API in-process, where the stateless `api` firewall is never traversed and the
 * token storage is therefore empty, while the Thelia session still holds the
 * customer who signed in. Reading only one of the two would leave one of the two
 * front paths priceless.
 *
 * Outside a request — the scheduled command that opens and closes operations,
 * a console command, a worker — there is neither, and this answers null, which
 * is exactly what a caller with no customer should get.
 */
class CurrentCustomerProvider
{
    public function __construct(
        private readonly SecurityContext $securityContext,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getCurrentCustomer(): ?Customer
    {
        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer) {
            $customer = $this->securityContext->getCustomerUser();
        }

        if (!$customer instanceof Customer) {
            return null;
        }

        // A guest is never entitled to a reserved operation. The shop reuses the guest
        // row behind an address, so it is shared with every earlier visitor who ordered
        // from that address: naming it on an operation would hand its price to anyone
        // who guest-checks-out with the same email.
        return $customer->isGuest() ? null : $customer;
    }
}
