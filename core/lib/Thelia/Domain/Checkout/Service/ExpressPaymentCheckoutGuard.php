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

namespace Thelia\Domain\Checkout\Service;

use Psr\Container\ContainerInterface;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Model\Cart;
use Thelia\Model\ModuleQuery;

/**
 * Refuses to place, through the ordinary checkout, an order whose payment module takes
 * express payment only.
 *
 * Such a module can be chosen at the payment step, in both display modes, and the order
 * button then becomes the wallet's button: the order is placed by the wallet's confirmation, once the provider
 * has taken the money. A request that reaches the ordinary placement with it anyway, a
 * forged one or a stale page, would call the module's pay() without a sheet and mark the
 * order paid with nothing taken.
 */
final readonly class ExpressPaymentCheckoutGuard
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @throws InvalidPaymentException
     */
    public function refuseAnOrdinaryPlacementOf(Cart $cart): void
    {
        $instance = null === $cart->getPaymentModuleId()
            ? null
            : ModuleQuery::create()->findPk($cart->getPaymentModuleId())?->getPaymentModuleInstance($this->container);

        if (null !== $instance && !CheckoutPaymentOffer::canBePaidAtCheckout($instance)) {
            throw new InvalidPaymentException('This payment method is paid with its own button.');
        }
    }
}
