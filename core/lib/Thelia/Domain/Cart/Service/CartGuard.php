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

namespace Thelia\Domain\Cart\Service;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Exception\EmptyCartException;
use Thelia\Domain\Checkout\Exception\IncompleteInvoiceAddressException;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Exception\MissingAddressException;
use Thelia\Domain\Legal\CompanyIdentifierRules;
use Thelia\Domain\Shipping\Service\DeliveryModuleEligibilityChecker;
use Thelia\Domain\Shipping\Service\DeliveryPostageQuerier;
use Thelia\Model\Cart;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

class CartGuard
{
    public function __construct(
        private readonly DeliveryModuleEligibilityChecker $deliveryModuleEligibilityChecker,
        private readonly DeliveryPostageQuerier $deliveryPostageQuerier,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @throws EmptyCartException
     * @throws PropelException
     */
    public function checkCartNotEmpty(?Cart $cart): void
    {
        if (!$cart || $cart->countCartItems() === 0) {
            throw new EmptyCartException('Cart is empty or contains no items');
        }
    }

    /**
     * @throws MissingAddressException
     */
    public function checkDeliveryAddress(?Cart $cart): void
    {
        if (!$cart || !$cart->getAddressDeliveryId()) {
            throw new MissingAddressException('Delivery address is required');
        }

        $address = CartAddressQuery::create()->findPk($cart->getAddressDeliveryId());
        if (!$address) {
            throw new MissingAddressException('Delivery address not found');
        }
    }

    /**
     * @throws MissingAddressException
     */
    public function checkInvoiceAddress(?Cart $cart): void
    {
        if (!$cart || !$cart->getAddressInvoiceId()) {
            throw new MissingAddressException('Invoice address is required');
        }

        $address = CartAddressQuery::create()->findPk($cart->getAddressInvoiceId());
        if (!$address) {
            throw new MissingAddressException('Invoice address not found');
        }
    }

    /**
     * An invoice for a business buyer has to carry its legal identifiers, so a billing address
     * that names a company without them cannot be ordered against. Checked on the cart address
     * rather than on the customer's own: that is what the order will be frozen from.
     *
     * The delivery address is deliberately not subject to this - it names a place, not a payer.
     *
     * @throws IncompleteInvoiceAddressException
     * @throws MissingAddressException
     */
    public function checkInvoiceAddressLegalIdentifiers(?Cart $cart): void
    {
        $this->checkInvoiceAddress($cart);

        $address = CartAddressQuery::create()->findPk($cart->getAddressInvoiceId());

        $violations = CompanyIdentifierRules::violationsFor(
            $address->getCompany(),
            $address->getSiret(),
            $address->getVatNumber(),
            $address->getCountry()?->getIsoalpha2(),
        );

        if ([] !== $violations) {
            throw new IncompleteInvoiceAddressException($violations[0]->message);
        }
    }

    /**
     * @throws InvalidDeliveryException
     */
    public function checkValidDelivery(?Cart $cart): void
    {
        $this->checkDeliveryAddress($cart);

        if (!$cart?->getDeliveryModuleId()) {
            throw new InvalidDeliveryException('Delivery module is required');
        }

        $module = ModuleQuery::create()->findPk($cart->getDeliveryModuleId());
        if (!$module) {
            throw new InvalidDeliveryException('Delivery module not found');
        }

        // The module the cart names was chosen by the customer: it has to be one the shop
        // would have offered for this cart and this address, judged the same way the
        // offer was built, or the order would ship under terms nobody quoted.
        if (!$module->isDeliveryModule() || !$this->isActive($module)) {
            throw new InvalidDeliveryException('Delivery module is not available');
        }

        $address = CartAddressQuery::create()->findPk($cart->getAddressDeliveryId());
        $country = $address?->getCountry();

        if (null === $address || null === $country) {
            throw new MissingAddressException('Delivery address has no country');
        }

        if (!$this->deliveryModuleEligibilityChecker->isEligible($module, $cart, $country, $address->getState())) {
            throw new InvalidDeliveryException('Delivery module does not deliver to this address');
        }

        $quote = $this->deliveryPostageQuerier->query($module, $cart, $address->getAddress(), $country, $address->getState());

        if (!$quote['valid']) {
            throw new InvalidDeliveryException('Delivery module does not accept this cart');
        }
    }

    /**
     * @throws InvalidPaymentException
     */
    public function checkValidPayment(?Cart $cart): void
    {
        if (!$cart || !$cart->getPaymentModuleId()) {
            throw new InvalidPaymentException('Payment module is required');
        }

        $module = ModuleQuery::create()->findPk($cart->getPaymentModuleId());
        if (!$module) {
            throw new InvalidPaymentException('Payment module not found');
        }

        if (!$module->isPayementModule() || !$this->isActive($module)) {
            throw new InvalidPaymentException('Payment module is not available');
        }

        // Same judge as the payment options offered at checkout.
        $isValidPaymentEvent = new IsValidPaymentEvent($module->getPaymentModuleInstance($this->container), $cart);
        $this->dispatcher->dispatch($isValidPaymentEvent, TheliaEvents::MODULE_PAYMENT_IS_VALID);

        if (!$isValidPaymentEvent->isValidModule()) {
            throw new InvalidPaymentException('Payment module does not accept this cart');
        }
    }

    private function isActive(Module $module): bool
    {
        return 1 === (int) $module->getActivate();
    }
}
