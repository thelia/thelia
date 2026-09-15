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

use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Order\DTO\OrderHistoryActor;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Model\Admin;
use Thelia\Model\Customer;

/**
 * Names the author of an order history entry.
 *
 * The order of the checks is the order of precedence: admin in session, then an
 * explicitly provided module code, then customer in session, then system.
 *
 * A back-office gesture stays the admin's even when it is dispatched through a module
 * (an admin triggering a manual refund through a payment module is still the admin).
 * But a module that names itself is trusted over the customer in session: a payment
 * gateway's synchronous return (BasePaymentModuleController::confirmPayment) runs
 * inside the customer's own HTTP session, and without this precedence a "paid" status
 * change would be attributed to the customer instead of the module that reported it.
 * Nothing here assumes an HTTP request exists — the console and the workers go through
 * the same code and land on SYSTEM, which is a real answer and not a failure to look.
 */
final readonly class OrderHistoryActorResolver
{
    public function __construct(
        private SecurityContext $securityContext,
    ) {
    }

    public function resolve(?string $moduleCode = null): OrderHistoryActor
    {
        $adminUser = $this->securityContext->getAdminUser();

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

        $customerUser = $this->securityContext->getCustomerUser();

        if ($customerUser instanceof Customer) {
            // The reference, never the email: the history is read by whoever opens the
            // order, and the customer reference identifies the buyer without carrying
            // personal data into a table nobody thinks of as personal.
            return new OrderHistoryActor(OrderHistoryActorType::CUSTOMER, $customerUser->getRef());
        }

        return OrderHistoryActor::system();
    }
}
