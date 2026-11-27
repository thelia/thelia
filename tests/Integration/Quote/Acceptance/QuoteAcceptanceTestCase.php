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

namespace Thelia\Tests\Integration\Quote\Acceptance;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Quote\Acceptance\QuoteAcceptance;
use Thelia\Domain\Quote\Acceptance\QuoteSellability;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Domain\Quote\QuoteTransitions;
use Thelia\Domain\Quote\Service\QuoteCatalogPrices;
use Thelia\Domain\Quote\Service\QuoteLineWriter;
use Thelia\Domain\Quote\Service\QuoteNoteSanitizer;
use Thelia\Domain\Quote\Service\QuoteRefAllocator;
use Thelia\Domain\Quote\Service\QuoteRevisionFreezer;
use Thelia\Domain\Quote\Service\QuoteRevisionGuard;
use Thelia\Domain\Quote\Service\QuoteTotalsCalculator;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\Company;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\Quote;
use Thelia\Tests\Integration\Quote\QuoteDomainTestCase;

/**
 * What the tests of the acceptance of a quote share: the facade that accepts, a quote the shop has
 * sent (12 of A at 90, 4 of B at 45 and 5 of C at 20, 30 off with taxes, 15 of postage without,
 * 20 % VAT: 1620.00 with taxes), the buyer who may order it, and the placement of an order.
 */
abstract class QuoteAcceptanceTestCase extends QuoteDomainTestCase
{
    /**
     * A company that has chosen its billing address, as a company must have for its buyers to order:
     * the address is shared with it by an account that does not belong to it.
     */
    protected function company(string $name = 'Acme'): Company
    {
        $company = parent::company($name);
        $address = $this->addressOf($this->customer(), $company, 'Headquarters');
        $company->setBillingAddressId((int) $address->getId())->save($this->getPropelConnection());

        return $company;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->quotes = new QuoteFacade(
            $this->getService(EventDispatcherInterface::class),
            $this->clock,
            new QuoteTransitions(),
            $this->quoteAccess,
            $this->getService(QuoteTotalsCalculator::class),
            $this->getService(QuoteRefAllocator::class),
            $this->getService(QuoteLineWriter::class),
            $this->getService(QuoteCatalogPrices::class),
            new QuoteNoteSanitizer(),
            new QuoteRevisionGuard(),
            $this->getService(QuoteRevisionFreezer::class),
            $this->getService(QuoteSellability::class),
            $this->getService(QuoteAcceptance::class),
        );

        // The consents the shop asks for at the checkout are not what these tests are about.
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();

        // The carrier delivers to the country of the shop, and the payment module is always valid.
        $area = (new Area())->setName('Quote acceptance area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId(Country::getDefaultCountry()->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->deliveryModuleId())->save($this->getPropelConnection());
        $this->getService(EventDispatcherInterface::class)->addListener(
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
            static function (IsValidPaymentEvent $event): void {
                $event->setValidModule(true);
                $event->stopPropagation();
            },
            512,
        );

        // The carrier would quote what it quotes: the postage of a quote is the one the shop negotiated.
        $this->getService(EventDispatcherInterface::class)->addListener(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            static function (DeliveryPostageEvent $event): void {
                $event->setValidModule(true);
                $event->setPostage(new OrderPostage(99.0, 0.0, 'VAT'));
                $event->stopPropagation();
            },
            512,
        );

        // The payment module would answer with a page of its own: only the placement matters.
        $this->getService(EventDispatcherInterface::class)->addListener(
            TheliaEvents::MODULE_PAY,
            static fn (OrderPaymentEvent $event) => $event->stopPropagation(),
            512,
        );
    }

    /**
     * A buyer with an address in the default country, which the quote is written with.
     */
    protected function buyerWithAnAddress(): Customer
    {
        $buyer = $this->customer();
        $this->fixtures->address($buyer, Country::getDefaultCountry())->setIsDefault(1)->save($this->getPropelConnection());

        return $buyer;
    }

    /**
     * A quote sent at its second revision, which the buyer can accept: valid until
     * the thirty-first of December, delivered by a carrier of the shop.
     *
     * @return array{buyer: Customer, quote: Quote, a: \Thelia\Model\Product, b: \Thelia\Model\Product, c: \Thelia\Model\Product}
     */
    protected function quotedScenario(?Customer $buyer = null): array
    {
        $buyer ??= $this->buyerWithAnAddress();
        $a = $this->product('100', 'Product A');
        $b = $this->product('50', 'Product B');
        $c = $this->product('20', 'Product C');
        $quote = $this->quotes->requestFromCart($this->cartOf($buyer, [[$a, 10.0, '100'], [$b, 4.0, '50']]), $buyer, new \Thelia\Domain\Quote\DTO\QuoteRequestInput());
        $scenario = ['buyer' => $buyer, 'quote' => $quote, 'a' => $a, 'b' => $b, 'c' => $c];

        $this->quotes->saveDraft($quote, null, $this->draftFrom(
            $this->revisionOf($quote, 1),
            [
                $a->getRef() => ['quantity' => 12.0, 'price' => '90'],
                $b->getRef() => ['price' => '45'],
            ],
            [new \Thelia\Domain\Quote\DTO\QuoteDraftLine(null, $this->saleElementId($c), 5.0, '20')],
            discount: '30',
            postage: '15',
            deliveryModuleId: $this->deliveryModuleId(),
        ));
        $this->quotes->send($quote, null);

        return $scenario;
    }

    /**
     * The addresses the checkout would have asked the buyer for, when the quote carries none.
     */
    protected function withAddresses(Cart $cart): Cart
    {
        $address = $this->fixtures->address($cart->getCustomer(), Country::getDefaultCountry());
        $delivery = new CartAddress();
        $delivery->copyFrom($address);
        $delivery->save($this->getPropelConnection());
        $invoice = new CartAddress();
        $invoice->copyFrom($address);
        $invoice->save($this->getPropelConnection());
        $cart
            ->setAddressDeliveryId($delivery->getId())
            ->setAddressInvoiceId($invoice->getId())
            ->save($this->getPropelConnection());

        return $cart;
    }

    protected function deliveryModuleId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId();
    }

    protected function paymentModuleId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('Cheque')?->getId();
    }

    protected function theCurrencyOf(Quote $quote): Currency
    {
        return CurrencyQuery::create()->findPk($quote->getCurrencyId()) ?? throw new \LogicException('The currency of the quote is gone.');
    }

    protected function reloaded(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();
        CartItemTableMap::clearInstancePool();

        return \Thelia\Model\CartQuery::create()->findPk($cart->getId()) ?? throw new \LogicException('The cart is gone.');
    }

    /**
     * The order the buyer places with the cart, the way the checkout does when the payment is chosen.
     */
    protected function pay(Cart $cart, ?Customer $buyer = null): Order
    {
        $cart = $this->reloaded($cart);
        $this->getService(SecurityContext::class)->setCustomerUser($buyer ?? $cart->getCustomer());
        $currency = CurrencyQuery::create()->findPk($cart->getCurrencyId());

        $order = $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $cart->getDeliveryModuleId(),
            $this->paymentModuleId(),
            $currency,
        ))->placedOrder;
        OrderTableMap::clearInstancePool();

        return $order;
    }
}
