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

namespace Thelia\Tests\Integration\Quote\Cart;

use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\CheckoutRefusedException;
use Thelia\Domain\Checkout\Exception\QuoteDeliveryImposedException;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Quote\Enum\QuoteRevisionAddressKind;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CheckoutStep;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\QuoteRevisionAddress;
use Thelia\Model\QuoteRevisionQuery;

/**
 * The cart of a quote ships the way the quote says: the carrier and the address it names are the
 * only ones the checkout accepts, and what the quote leaves open is asked once and then held. The
 * postage is taxed where the cart really ships.
 */
final class QuoteCartDeliveryTest extends QuoteCartTestCase
{
    public function testAnotherCarrierThanTheOneOfTheQuoteIsRefusedAndTheSameOneGoesThrough(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);

        $this->facade()->setDeliveryModule(new CheckoutDTO($cart, deliveryModuleId: $this->carrier()));
        self::assertSame($this->carrier(), $this->reloaded($cart)->getDeliveryModuleId());

        $this->assertRefused(QuoteDeliveryImposedException::FIELD_MODULE, fn () => $this->facade()->setDeliveryModule(new CheckoutDTO($this->reloaded($cart), deliveryModuleId: $this->otherCarrier())));

        self::assertSame($this->carrier(), $this->reloaded($cart)->getDeliveryModuleId(), 'the cart was left as it was');
    }

    public function testAnotherAddressThanTheOneOfTheQuoteIsRefusedAndTheSameOneGoesThrough(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);
        $quoteAddress = $this->reloaded($cart)->getCartAddressRelatedByAddressDeliveryId()->getAddressId();

        $this->facade()->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $quoteAddress));

        $another = $this->factory->address($this->buyer, Country::getDefaultCountry());
        $this->assertRefused(QuoteDeliveryImposedException::FIELD_ADDRESS, fn () => $this->facade()->setDeliveryAddress(new CheckoutDTO($this->reloaded($cart), deliveryAddressId: $another->getId())));

        self::assertSame($quoteAddress, $this->reloaded($cart)->getCartAddressRelatedByAddressDeliveryId()->getAddressId(), 'the cart was left as it was');
    }

    public function testAQuoteWithNoCarrierTakesTheFirstChoiceAndHoldsIt(): void
    {
        $cart = $this->quoteCartShippingWith(null, withAnAddress: true);
        self::assertNull($this->reloaded($cart)->getDeliveryModuleId());

        $this->facade()->setDeliveryModule(new CheckoutDTO($cart, deliveryModuleId: $this->carrier()));
        self::assertSame($this->carrier(), $this->reloaded($cart)->getDeliveryModuleId(), 'the first choice');

        $this->facade()->setDeliveryModule(new CheckoutDTO($this->reloaded($cart), deliveryModuleId: $this->carrier()));
        $this->assertRefused(QuoteDeliveryImposedException::FIELD_MODULE, fn () => $this->facade()->setDeliveryModule(new CheckoutDTO($this->reloaded($cart), deliveryModuleId: $this->otherCarrier())));
        self::assertSame($this->carrier(), $this->reloaded($cart)->getDeliveryModuleId(), 'held on the cart');
    }

    public function testAQuoteWithNoAddressTakesTheFirstChoiceAndHoldsIt(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: false);
        self::assertNull($this->reloaded($cart)->getAddressDeliveryId());
        $first = $this->factory->address($this->buyer, Country::getDefaultCountry());
        $second = $this->factory->address($this->buyer, Country::getDefaultCountry());

        $this->facade()->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $first->getId()));
        self::assertSame($first->getId(), $this->reloaded($cart)->getCartAddressRelatedByAddressDeliveryId()->getAddressId(), 'the first choice');

        $this->assertRefused(QuoteDeliveryImposedException::FIELD_ADDRESS, fn () => $this->facade()->setDeliveryAddress(new CheckoutDTO($this->reloaded($cart), deliveryAddressId: $second->getId())));
        self::assertSame($first->getId(), $this->reloaded($cart)->getCartAddressRelatedByAddressDeliveryId()->getAddressId(), 'held on the cart');
    }

    public function testAnAddressTypedInAtTheCheckoutFollowsTheSameRule(): void
    {
        $open = $this->quoteCartShippingWith($this->carrier(), withAnAddress: false);
        $typed = $this->typedAddress();
        $this->dispatchTyped($open, $typed);
        self::assertSame($typed->getId(), $this->reloaded($open)->getAddressDeliveryId(), 'the first one goes through on a quote with no address');

        $this->assertRefused(QuoteDeliveryImposedException::FIELD_ADDRESS, fn () => $this->dispatchTyped($this->reloaded($open), $this->typedAddress()));
        self::assertSame($typed->getId(), $this->reloaded($open)->getAddressDeliveryId());

        $named = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);
        $before = $this->reloaded($named)->getAddressDeliveryId();
        $this->assertRefused(QuoteDeliveryImposedException::FIELD_ADDRESS, fn () => $this->dispatchTyped($this->reloaded($named), $this->typedAddress()));
        self::assertSame($before, $this->reloaded($named)->getAddressDeliveryId());
    }

    public function testTheCartOfAnOrdinaryOrderChoosesFreely(): void
    {
        $cart = $this->ordinaryCart();
        $another = $this->factory->address($this->buyer, Country::getDefaultCountry());

        $this->facade()->setDeliveryModule(new CheckoutDTO($cart, deliveryModuleId: $this->otherCarrier()));
        $this->facade()->setDeliveryAddress(new CheckoutDTO($this->reloaded($cart), deliveryAddressId: $another->getId()));

        $cart = $this->reloaded($cart);
        self::assertSame($this->otherCarrier(), $cart->getDeliveryModuleId());
        self::assertSame($another->getId(), $cart->getCartAddressRelatedByAddressDeliveryId()->getAddressId());
    }

    public function testThePostageIsTaxedWhereTheCartShipsWhenTheQuoteNamesNoAddress(): void
    {
        $inTheShopCountry = $this->quoteCartShippingWith($this->carrier(), withAnAddress: false);
        $this->facade()->setDeliveryAddress(new CheckoutDTO($inTheShopCountry, deliveryAddressId: $this->factory->address($this->buyer, Country::getDefaultCountry())->getId()));

        $postage = $this->reloaded($inTheShopCountry);
        self::assertSame(15.0, $postage->getUntaxedPostage());
        self::assertSame(3.0, (float) $postage->getPostageTax(), '20 % where the shop sells');

        $abroad = $this->quoteCartShippingWith($this->carrier(), withAnAddress: false);
        $this->facade()->setDeliveryAddress(new CheckoutDTO($abroad, deliveryAddressId: $this->factory->address($this->buyer, $this->countryOutsideTheRule())->getId()));

        $postage = $this->reloaded($abroad);
        self::assertSame(15.0, (float) $postage->getPostage(), 'the postage the quote carries');
        self::assertSame(0.0, (float) $postage->getPostageTax(), 'no rule of the shop covers that country');
    }

    public function testThePostageKeepsTheTaxesItWasFrozenWithWhenTheCartShipsWhereTheQuoteSays(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);
        QuoteRevisionQuery::create()
            ->filterByQuoteId($this->quote->getId())
            ->filterByNumber(2)
            ->update(['ItemsUntaxed' => '1080.000000', 'ItemsTax' => '216.000000', 'PostageTax' => '4.000000', 'TotalTaxed' => '1270.000000', 'TotalUntaxed' => '1065.000000']);
        \Thelia\Model\Map\QuoteRevisionTableMap::clearInstancePool();

        $this->facade()->setDeliveryModule(new CheckoutDTO($this->reloaded($cart), deliveryModuleId: $this->carrier()));

        $postage = $this->reloaded($cart);
        self::assertSame(4.0, (float) $postage->getPostageTax(), 'the tax of the revision as it was frozen, not worked out again');
        self::assertSame(19.0, (float) $postage->getPostage());
    }

    public function testThePostageIsTaxedAgainWhenTheAddressOfTheQuoteWasMovedToAnotherCountryAfterTheQuoteWasSent(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($this->quote->getId())->filterByNumber(2)->findOne();
        $revision
            ->setItemsUntaxed('1080.000000')
            ->setItemsTax('216.000000')
            ->setPostageTax('4.000000')
            ->setTotalTaxed('1270.000000')
            ->setTotalUntaxed('1065.000000')
            ->save($this->getPropelConnection());
        (new QuoteRevisionAddress())
            ->setQuoteRevisionId($revision->getId())
            ->setKind(QuoteRevisionAddressKind::Delivery->value)
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->save($this->getPropelConnection());

        // The buyer moves the address of the quote abroad, and the cart copies it so.
        $onTheCart = $this->reloaded($cart)->getCartAddressRelatedByAddressDeliveryId();
        AddressQuery::create()->findPk($onTheCart->getAddressId())->setCountryId($this->countryOutsideTheRule()->getId())->save($this->getPropelConnection());
        $onTheCart->setCountryId($this->countryOutsideTheRule()->getId())->save($this->getPropelConnection());

        $this->facade()->setDeliveryModule(new CheckoutDTO($this->reloaded($cart), deliveryModuleId: $this->carrier()));

        $postage = $this->reloaded($cart);
        self::assertSame(0.0, (float) $postage->getPostageTax(), 'no rule of the shop covers the country the cart ships to now');
        self::assertSame(15.0, (float) $postage->getPostage(), 'the postage itself is the one of the revision');
    }

    public function testACarrierTheShopSwitchedOffAfterTheQuoteWasSentIsNoLongerImposedAndTheFirstChoiceIsHeld(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);
        ModuleQuery::create()->filterById($this->carrier())->update(['Activate' => 0]);

        $this->facade()->setDeliveryModule(new CheckoutDTO($this->reloaded($cart), deliveryModuleId: $this->otherCarrier()));
        self::assertSame($this->otherCarrier(), $this->reloaded($cart)->getDeliveryModuleId(), 'the quote names a carrier that is gone: the buyer chooses');

        $this->assertRefused(QuoteDeliveryImposedException::FIELD_MODULE, fn () => $this->facade()->setDeliveryModule(new CheckoutDTO($this->reloaded($cart), deliveryModuleId: $this->carrier())));
        self::assertSame($this->otherCarrier(), $this->reloaded($cart)->getDeliveryModuleId(), 'and the choice is held');
    }

    public function testThePlacementRefusesACartThatShipsAnotherWayThanItsQuoteInTheShapeOfAnyOtherRefusal(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);
        $cart->setDeliveryModuleId($this->otherCarrier())->save($this->getPropelConnection());

        try {
            $this->placement()->place($this->placementOf($this->reloaded($cart)));
            self::fail('A cart that ships another way than its quote was placed.');
        } catch (CheckoutRefusedException $refused) {
            self::assertCount(1, $refused->violations);
            self::assertSame(CheckoutViolationCode::QuoteDeliveryImposed->value, $refused->violations[0]->code);
            self::assertSame(CheckoutStep::CODE_DELIVERY, $refused->violations[0]->stepCode);
            self::assertSame(['field' => 'deliveryModule'], $refused->violations[0]->details);
        }

        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testThePlacementRefusesACartThatShipsToAnotherAddressThanItsQuote(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);
        $other = $this->factory->cartAddress($this->factory->address($this->buyer, Country::getDefaultCountry()));
        $cart->setAddressDeliveryId($other->getId())->save($this->getPropelConnection());

        try {
            $this->placement()->place($this->placementOf($this->reloaded($cart)));
            self::fail('A cart that ships to another address than its quote was placed.');
        } catch (CheckoutRefusedException $refused) {
            self::assertSame(CheckoutViolationCode::QuoteDeliveryImposed->value, $refused->violations[0]->code);
            self::assertSame(['field' => 'deliveryAddress'], $refused->violations[0]->details);
        }

        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheOrderCannotBeWrittenWithAnotherCarrierThanTheOneOfTheQuoteWhoeverAsks(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);

        $this->assertRefused(QuoteDeliveryImposedException::FIELD_MODULE, fn () => $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            $this->otherCarrier(),
            (int) $cart->getPaymentModuleId(),
        )));

        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheOrderCannotBeWrittenToAnotherAddressThanTheOneOfTheQuote(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);
        $other = $this->factory->cartAddress($this->factory->address($this->buyer, Country::getDefaultCountry()));
        $cart->setAddressDeliveryId($other->getId())->save($this->getPropelConnection());

        $this->assertRefused(QuoteDeliveryImposedException::FIELD_ADDRESS, fn () => $this->pay($cart));
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheCartThatShipsAsItsQuoteSaysIsOrdered(): void
    {
        $cart = $this->quoteCartShippingWith($this->carrier(), withAnAddress: true);

        self::assertSame($this->quote->getId(), $this->pay($cart)->getQuoteId());
    }

    /**
     * The cart of the quote of the shop (see {@see quoteCart()}), with the quote naming the given
     * carrier, or none, and the address the cart ships to, or none: the quote names an address when
     * the cart was built with it.
     */
    private function quoteCartShippingWith(?int $quoteCarrier, bool $withAnAddress): Cart
    {
        $cart = $this->quoteCart();
        $address = $this->reloaded($cart)->getCartAddressRelatedByAddressDeliveryId();
        $this->quote
            ->setDeliveryModuleId($quoteCarrier)
            ->setDeliveryAddressId($withAnAddress ? $address->getAddressId() : null)
            ->save($this->getPropelConnection());
        $cart->setDeliveryModuleId($quoteCarrier);

        if (!$withAnAddress) {
            $cart->setAddressDeliveryId(null);
        }

        $cart->save($this->getPropelConnection());

        return $this->reloaded($cart);
    }

    private function assertRefused(string $field, callable $selection): void
    {
        try {
            $selection();
            self::fail('The cart of a quote accepted a delivery other than the one of its quote.');
        } catch (QuoteDeliveryImposedException $refusal) {
            self::assertSame(CheckoutViolationCode::QuoteDeliveryImposed->value, $refusal->violationCode());
            self::assertSame(['field' => $field], $refusal->violationDetails());
        }
    }

    private function typedAddress(): CartAddress
    {
        return (new CartAddress())
            ->setCustomerTitleId($this->factory->customerTitle()->getId())
            ->setFirstname('Typed')
            ->setLastname('In')
            ->setAddress1('1 Typed Street')
            ->setAddress2('')
            ->setAddress3('')
            ->setZipcode('75001')
            ->setCity('Paris')
            ->setCountryId(Country::getDefaultCountry()->getId());
    }

    private function dispatchTyped(Cart $cart, CartAddress $address): void
    {
        $this->dispatcher->dispatch((new CartCheckoutEvent($cart))->setCartAddress($address), TheliaEvents::CART_SET_DELIVERY_ADDRESS_MANUAL);
    }

    private function countryOutsideTheRule(): Country
    {
        return CountryQuery::create()->findOneByIsoalpha2('BE') ?? throw new \LogicException('The test database has no Belgium.');
    }

    private function placementOf(Cart $cart): CheckoutPlacementRequest
    {
        return new CheckoutPlacementRequest($cart, $this->buyer, $this->factory->currency(), $this->factory->lang());
    }

    private function placement(): CheckoutPlacementService
    {
        return $this->getService(CheckoutPlacementService::class);
    }

    private function facade(): CartFacade
    {
        return $this->getService(CartFacade::class);
    }

    private function carrier(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId();
    }

    private function otherCarrier(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId();
    }
}
