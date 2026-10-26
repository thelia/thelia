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

namespace Thelia\Domain\Order\Service;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Order\DTO\OrderHistoryActor;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Model\Admin;
use Thelia\Model\Customer;

/**
 * Names the author of an order history entry.
 *
 * The order of the checks is the order of precedence: admin (the one of the security
 * token, else the one in session), then an explicitly provided module code, then
 * customer (the one of the security token, else the one in session), then system. A
 * token that names an admin or a customer closes the session altogether: a customer
 * JWT is not signed by the administrator whose cookie came along.
 *
 * The token comes first because it is the proof of the request, and the session only
 * what the browser happened to send along. The token is filled by the stateless
 * firewalls of the API alone (`api`, `frontLogin` and `adminLogin`, declared by
 * TheliaKernel::loadDefaultSecurityConfig()), which authenticate a JWT; in the
 * configuration the core ships, the firewall of the shop and of the back office has no
 * authenticator, and nothing else in the core sets a token. Outside /api the token is
 * therefore empty and the session answers as it always did. A project that puts an
 * authenticator on `main` gets its token read before the session: that is intended, the
 * token is the proof of the request wherever it was authenticated. On /api the session cookie of the browser still reaches the
 * request (its path is /), so an administrator signed in to the back office who calls
 * the API with the JWT of another account would be named after his own session were
 * the session read first. A request of the API is authenticated by its JWT, and by
 * nothing else. A user of the token that is neither an Admin nor a Customer (the
 * in-memory user of a firewall that has none of ours) names nobody.
 *
 * A back-office gesture stays the admin's even when it is dispatched through a module
 * (an admin triggering a manual refund through a payment module is still the admin).
 * But a module that names itself is trusted over the customer: a payment gateway's
 * synchronous return (BasePaymentModuleController::confirmPayment) runs inside the
 * customer's own HTTP session, and without this precedence a "paid" status change
 * would be attributed to the customer instead of the module that reported it.
 * Nothing here assumes an HTTP request exists — the console and the workers go through
 * the same code and land on SYSTEM, which is a real answer and not a failure to look.
 */
final readonly class OrderHistoryActorResolver
{
    public function __construct(
        private SecurityContext $securityContext,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function resolve(?string $moduleCode = null): OrderHistoryActor
    {
        $tokenUser = $this->tokenUser();

        // A token that names somebody closes the session: the request is that user's,
        // whoever else the browser is signed in as.
        $sessionCounts = !$tokenUser instanceof Admin && !$tokenUser instanceof Customer;
        $adminUser = $tokenUser instanceof Admin ? $tokenUser : ($sessionCounts ? $this->securityContext->getAdminUser() : null);

        if ($adminUser instanceof UserInterface) {
            return new OrderHistoryActor(
                OrderHistoryActorType::ADMIN,
                $adminUser->getUsername(),
                $adminUser instanceof Admin ? $adminUser->getId() : null,
            );
        }

        if (null !== $moduleCode && '' !== $moduleCode) {
            return new OrderHistoryActor(OrderHistoryActorType::MODULE, $moduleCode);
        }

        $customerUser = $tokenUser instanceof Customer ? $tokenUser : ($sessionCounts ? $this->securityContext->getCustomerUser() : null);

        if ($customerUser instanceof Customer) {
            // The reference, never the email: the history is read by whoever opens the
            // order, and the customer reference identifies the buyer without carrying
            // personal data into a table nobody thinks of as personal.
            return new OrderHistoryActor(OrderHistoryActorType::CUSTOMER, $customerUser->getRef());
        }

        return OrderHistoryActor::system();
    }

    /**
     * The user the security token holds: the one a JWT authenticated on /api, none
     * anywhere else and in the console.
     */
    private function tokenUser(): ?SecurityUserInterface
    {
        return $this->tokenStorage->getToken()?->getUser();
    }
}
