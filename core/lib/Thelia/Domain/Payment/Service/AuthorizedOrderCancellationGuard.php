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

namespace Thelia\Domain\Payment\Service;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\Service\OrderHistoryActorResolver;
use Thelia\Domain\Payment\Exception\CancellationNeedsCaptureRightException;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;

/**
 * Cancelling an order whose authorization still holds an amount releases that amount at
 * the provider. An administrator does it only with the right to capture payments, the
 * same right the capture asks for; a module, a customer or the shop itself is not asked.
 */
final readonly class AuthorizedOrderCancellationGuard
{
    public function __construct(
        private OrderHistoryActorResolver $actorResolver,
        private SecurityContext $securityContext,
        private PaymentTransactionTotalsReader $totalsReader,
    ) {
    }

    /**
     * @throws CancellationNeedsCaptureRightException
     */
    public function assertMayMoveTo(Order $order, int $toStatusId, ?string $moduleCode = null): void
    {
        if ($order->getOrderStatus()->isCancelled(false)) {
            return;
        }

        $toStatus = OrderStatusQuery::create()->findPk($toStatusId);

        if (null === $toStatus || !$toStatus->isCancelled(false)) {
            return;
        }

        $administrator = $this->actorResolver->actingAdministrator($moduleCode);

        if (null === $administrator || !$this->totalsReader->forOrder((int) $order->getId())->hasSomethingLeftToCapture()) {
            return;
        }

        if (!$this->securityContext->isUserGranted(['ADMIN'], [AdminResources::ORDER_PAYMENT_CAPTURE], [], [AccessManager::CREATE], $administrator)) {
            throw new CancellationNeedsCaptureRightException((string) $order->getRef());
        }
    }
}
