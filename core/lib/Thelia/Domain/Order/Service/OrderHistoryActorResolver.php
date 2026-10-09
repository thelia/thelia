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

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\RequestPath;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Order\DTO\OrderHistoryActor;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Model\Admin;
use Thelia\Model\Customer;

/**
 * Names the author of an order history entry.
 *
 * The order of the checks is the order of precedence: the administrator the request
 * authenticated by token, then the administrator in session, then an explicitly provided
 * module code, then the customer in session, then system.
 *
 * The token comes first: it is what authenticated the request at hand, while a
 * back-office session may only share the browser. A back-office gesture stays the admin's
 * even when it is dispatched through a module (an admin triggering a manual refund
 * through a payment module is still the admin). Outside the back office, a module that
 * names itself is trusted over whoever is in session: a payment gateway's synchronous
 * return (BasePaymentModuleController::confirmPayment) runs inside the buyer's own HTTP
 * session — possibly one where an administrator is signed in too — and without this
 * precedence a "paid" status change would be attributed to them instead of the module
 * that reported it. Nothing here assumes an HTTP request exists — the console and the
 * workers go through the same code and land on SYSTEM, which is a real answer and not a
 * failure to look.
 */
final readonly class OrderHistoryActorResolver
{
    /**
     * The Symfony security token is where an administrator authenticated on the admin
     * API by a JWT is found: that administrator holds no back-office session. Optional
     * so that the resolver still works where the security bundle is not wired.
     */
    public function __construct(
        private SecurityContext $securityContext,
        private ?Security $security = null,
        private ?RequestStack $requestStack = null,
    ) {
    }

    public function resolve(?string $moduleCode = null): OrderHistoryActor
    {
        $hasModuleCode = null !== $moduleCode && '' !== $moduleCode;
        $adminUser = $this->actingAdministrator($moduleCode);

        if (null !== $adminUser) {
            return new OrderHistoryActor(
                OrderHistoryActorType::ADMIN,
                $adminUser->getUsername(),
                $adminUser instanceof Admin ? $adminUser->getId() : null,
            );
        }

        if ($hasModuleCode) {
            return new OrderHistoryActor(OrderHistoryActorType::MODULE, $moduleCode);
        }

        $customerUser = $this->securityContext->getCustomerUser();

        if ($customerUser instanceof Customer) {
            // The reference, never the email: the history is read by whoever opens the
            // order, and the customer reference identifies the buyer without carrying
            // personal data into a table nobody thinks of as personal.
            return new OrderHistoryActor(OrderHistoryActorType::CUSTOMER, $customerUser->getRef());
        }

        return OrderHistoryActor::system();
    }

    /**
     * The administrator the change is made by, under the precedence above, or null when
     * it is made by a module, a customer or the shop itself.
     */
    public function actingAdministrator(?string $moduleCode = null): ?UserInterface
    {
        // Only an administrator: a customer holding a front API token is the customer.
        $tokenUser = $this->security?->getUser();

        if ($tokenUser instanceof Admin) {
            return $tokenUser;
        }

        if (null !== $moduleCode && '' !== $moduleCode && !$this->isBackOfficeOrUnknownRequest()) {
            return null;
        }

        $sessionAdmin = $this->securityContext->getAdminUser();

        return $sessionAdmin instanceof UserInterface ? $sessionAdmin : null;
    }

    /**
     * Without a request to look at, the administrator in session keeps the precedence it
     * always had.
     */
    private function isBackOfficeOrUnknownRequest(): bool
    {
        $request = $this->requestStack?->getMainRequest();

        if (null === $request) {
            return true;
        }

        $path = RequestPath::decoded($request);

        return '/admin' === $path || str_starts_with($path, '/admin/');
    }
}
