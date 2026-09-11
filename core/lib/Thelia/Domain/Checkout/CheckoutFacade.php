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

namespace Thelia\Domain\Checkout;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Checkout\Service\CheckoutResetService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Cart;
use Thelia\Model\Order;

final readonly class CheckoutFacade
{
    public function __construct(
        private CartSelectionService $cartSelectionService,
        private CheckoutValidationService $validationService,
        private CheckoutResetService $checkoutResetService,
        private CheckoutPaymentService $paymentService,
        private ShippingFacade $shippingFacade,
        private CheckoutProgressionService $progressionService,
    ) {
    }

    /**
     * Select the delivery address on the cart and refresh shipping.
     */
    public function selectDeliveryAddress(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setDeliveryAddress($dto);
    }

    /**
     * Select the invoice address on the cart and refresh shipping.
     */
    public function selectInvoiceAddress(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setInvoiceAddress($dto);
    }

    /**
     * Select the delivery module on the cart and refresh shipping.
     */
    public function selectDeliveryModule(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setDeliveryModule($dto);
    }

    /**
     * Select the payment module on the cart and refresh shipping.
     */
    public function selectPaymentModule(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setPaymentModule($dto);
    }

    /**
     * Validate the cart is ready for order placement: every step the installed code
     * declares is asked about it, in the order of the tunnel, whatever the merchant
     * turned off — removing a step removes its screen, never its check.
     *
     * @throws \Exception If underlying services raise domain exceptions
     */
    public function validateForOrder(Cart $cart): void
    {
        $this->validationService->validateForOrder($cart);
    }

    /**
     * Gives a cart with nothing to ship the delivery the order cannot be placed without,
     * and does nothing to any other cart.
     *
     * The delivery step is left out of the tunnel of such a cart — there is no question
     * to ask the buyer — and that is exactly why this exists: the order is still refused
     * while the cart names no carrier and no address, and the screen that used to set
     * them is the one the buyer no longer sees. A theme calls it as the buyer reaches the
     * step that follows the cart, which is the moment the delivery would have been
     * settled; the rule itself is not a theme's to own, since every theme, the front API
     * and a command line meet the same refusal at placement.
     *
     * A cart already naming a carrier is settled and left untouched, so calling this on
     * every page of the tunnel writes nothing after the first time.
     *
     * @throws PropelException
     */
    public function settleVirtualDeliveryIfNeeded(Cart $cart): void
    {
        if (null !== $cart->getDeliveryModuleId() || !$cart->isVirtual()) {
            return;
        }

        $this->shippingFacade->setupVirtualDelivery($cart);

        // The cart now has a carrier it did not have a line ago, and the progression
        // memoized the tunnel of the cart as it was.
        $this->progressionService->forget();
    }

    /**
     * Reset checkout selections on the given cart and clear postage.
     */
    public function resetCheckout(): void
    {
        $this->checkoutResetService->reset();
    }

    /**
     * Place and pay the order based on checkout selections.
     *
     * Expects the DTO to carry all necessary identifiers (deliveryAddressId, invoiceAddressId,
     * deliveryModuleId, paymentModuleId) already chosen on the cart.
     *
     * @return Response|null The payment response if available
     *
     * @throws \Exception If underlying services raise domain exceptions
     */
    public function pay(CheckoutDTO $dto): ?Response
    {
        $this->validateForOrder($dto->getCart());

        return $this->paymentService->pay(
            $dto->getCart(),
            $dto->getDeliveryAddressId(),
            $dto->getInvoiceAddressId(),
            $dto->getDeliveryModuleId() ?? $dto->getCart()->getDeliveryModuleId(),
            $dto->getPaymentModuleId() ?? $dto->getCart()->getPaymentModuleId(),
        );
    }

    /**
     * Cancel the current order.
     *
     * The tracking token stands in for the session a guest no longer has when they come
     * back from a payment that failed.
     *
     * @throws PropelException|\InvalidArgumentException
     */
    public function cancelOrder(int $orderId, ?string $guestOrderToken = null): Order
    {
        return $this->paymentService->cancel($orderId, $guestOrderToken);
    }
}
