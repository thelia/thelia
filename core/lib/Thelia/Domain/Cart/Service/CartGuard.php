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
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Checkout\Exception\DeliveryAddressMovedException;
use Thelia\Domain\Checkout\Exception\DeliveryExcludedByProductException;
use Thelia\Domain\Checkout\Exception\DestinationRestrictedException;
use Thelia\Domain\Checkout\Exception\EmptyCartException;
use Thelia\Domain\Checkout\Exception\IncompleteInvoiceAddressException;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Exception\MissingAddressException;
use Thelia\Domain\Legal\CompanyIdentifierRules;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateGuard;
use Thelia\Domain\Shipping\DestinationRestriction\CartDestinations;
use Thelia\Domain\Shipping\DestinationRestriction\Destination;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Domain\Shipping\Service\DeliveryModuleEligibilityChecker;
use Thelia\Domain\Shipping\Service\DeliveryPostageQuerier;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

class CartGuard
{
    public function __construct(
        private readonly DeliveryModuleEligibilityChecker $deliveryModuleEligibilityChecker,
        private readonly DeliveryPostageQuerier $deliveryPostageQuerier,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ContainerInterface $container,
        private readonly DeliveryDateGuard $deliveryDateGuard,
        private readonly CartShippingRules $cartShippingRules,
        private readonly ?DestinationRestrictionChecker $destinationRestrictionChecker = null,
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
        $this->refuseADeliveryAddressMovedSinceItWasChosen($cart);

        // Before anything about the carrier: no carrier may take a product its destination
        // refuses, and the buyer has to be told which product is in the way.
        $this->refuseARestrictedDestination($cart);

        if (!$cart?->getDeliveryModuleId()) {
            $this->refuseProductsNoCarrierTakes($cart);

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

        // Named before the generic refusal: the buyer has to know which product is in the way.
        $this->refuseACarrierTheProductsExclude($cart, (int) $module->getId());

        if (!$this->deliveryModuleEligibilityChecker->isEligible($module, $cart, $country, $address->getState(), $address->getZipcode())) {
            throw new InvalidDeliveryException('Delivery module does not deliver to this address');
        }

        $quote = $this->deliveryPostageQuerier->query($module, $cart, $address->getAddress(), $country, $address->getState());

        if (!$quote['valid']) {
            throw new InvalidDeliveryException('Delivery module does not accept this cart');
        }

        // Last, once the carrier itself is settled: the day only means something for a
        // carrier that will ship this cart to this address.
        $this->deliveryDateGuard->check($cart, $module);
    }

    /**
     * The cart ships to its own copy of the delivery address, and its carrier and postage
     * were quoted for the place the copy names. An address moved since, from the customer
     * account or through the API, has to be chosen again so that they are asked again: left
     * there, the order would leave with a carrier chosen for the former postal code.
     *
     * A cart with nothing to ship is not held back: the buyer is never shown a delivery step
     * to choose the address again on. A copy of no customer address (a guest's, an address
     * typed at checkout) or of an address since deleted has nothing to be compared with.
     *
     * @throws DeliveryAddressMovedException
     */
    private function refuseADeliveryAddressMovedSinceItWasChosen(Cart $cart): void
    {
        if ($cart->isVirtual()) {
            return;
        }

        $copy = CartAddressQuery::create()->findPk($cart->getAddressDeliveryId());
        $address = null !== $copy?->getAddressId() ? AddressQuery::create()->findPk($copy->getAddressId()) : null;

        if (null !== $address && !$copy->isAtThePlaceOf($address)) {
            throw new DeliveryAddressMovedException();
        }
    }

    /**
     * The one refusal that holds whatever path placed the order, theme or API: the earlier
     * ones, at the cart and at the address, only save the buyer some time. A download is
     * judged on the invoice address of the cart, never on the default address of the
     * customer: without an invoice address the payment step refuses the order anyway.
     *
     * @throws DestinationRestrictedException
     */
    private function refuseARestrictedDestination(Cart $cart): void
    {
        if (null === $this->destinationRestrictionChecker || !$this->destinationRestrictionChecker->hasActiveGroup()) {
            return;
        }

        $address = CartAddressQuery::create()->findPk($cart->getAddressDeliveryId());

        if (null === $address) {
            return;
        }

        $restrictedLines = $this->destinationRestrictionChecker->violationsFor(
            $cart,
            Destination::of($address),
            CartDestinations::billing($cart),
            $this->locale(),
        );

        if ([] !== $restrictedLines) {
            throw new DestinationRestrictedException($restrictedLines);
        }
    }

    /**
     * Refuses a carrier the products of the cart exclude, naming them: a buyer may not
     * pick a carrier the shop declared unable to take what is in the cart.
     *
     * @throws DeliveryExcludedByProductException
     */
    public function refuseACarrierTheProductsExclude(Cart $cart, int $moduleId): void
    {
        $excludingProductIds = $this->cartShippingRules->for($cart)->productsExcluding($moduleId);

        if ([] === $excludingProductIds) {
            return;
        }

        $address = null !== $cart->getAddressDeliveryId() ? CartAddressQuery::create()->findPk($cart->getAddressDeliveryId()) : null;
        $country = $address?->getCountry();
        $noCarrierLeft = null !== $address && null !== $country
            && [] !== $this->deliveryModuleEligibilityChecker->productsBlockingEveryCarrier($cart, $country, $address->getState(), $address->getZipcode());

        throw new DeliveryExcludedByProductException($this->cartShippingRules->titlesOf($excludingProductIds, $this->locale()), $noCarrierLeft);
    }

    /**
     * A cart no carrier can take, because of its products, is told which ones.
     *
     * @throws DeliveryExcludedByProductException
     */
    private function refuseProductsNoCarrierTakes(Cart $cart): void
    {
        $address = CartAddressQuery::create()->findPk($cart->getAddressDeliveryId());
        $country = $address?->getCountry();

        if (null === $address || null === $country) {
            return;
        }

        $blockingProductIds = $this->deliveryModuleEligibilityChecker->productsBlockingEveryCarrier($cart, $country, $address->getState(), $address->getZipcode());

        if ([] !== $blockingProductIds) {
            throw new DeliveryExcludedByProductException($this->cartShippingRules->titlesOf($blockingProductIds, $this->locale()), true);
        }
    }

    private function locale(): string
    {
        return Translator::getInstance()->getLocale() ?: Lang::getDefaultLanguage()->getLocale();
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
