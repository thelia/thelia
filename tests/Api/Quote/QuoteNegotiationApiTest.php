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

namespace Thelia\Tests\Api\Quote;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\QuoteQuery;

/**
 * The whole negotiation over HTTP, with the figures the shop and the buyer agreed on: the request, the
 * answer of the shop, the counter-proposal, the answer again, the acceptance and the order the buyer
 * places with the cart they were given, which carries what was negotiated and not the catalogue.
 */
final class QuoteNegotiationApiTest extends QuoteApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The consents the shop asks for at the checkout are not what these tests are about.
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();

        $area = (new Area())->setName('Quote negotiation area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId(Country::getDefaultCountry()->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->deliveryModuleId())->save($this->getPropelConnection());

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
            static function (IsValidPaymentEvent $event): void {
                $event->setValidModule(true);
                $event->stopPropagation();
            },
            512,
        );
        $dispatcher->addListener(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            static function (DeliveryPostageEvent $event): void {
                $event->setValidModule(true);
                $event->setPostage(new OrderPostage(99.0, 0.0, 'VAT'));
                $event->stopPropagation();
            },
            512,
        );
        $dispatcher->addListener(TheliaEvents::MODULE_PAY, static fn (OrderPaymentEvent $event) => $event->stopPropagation(), 512);
    }

    public function testTheShopAndTheBuyerAgreeOnTheQuoteAndTheOrderCarriesWhatWasAgreed(): void
    {
        $buyer = $this->customer();
        $this->fixtures->address($buyer, Country::getDefaultCountry())->setIsDefault(1)->save($this->getPropelConnection());
        $a = $this->product('100', 'Product A');
        $b = $this->product('50', 'Product B');
        $c = $this->product('20', 'Product C');
        $cart = $this->cartOf($buyer, [[$a, 10.0, '100'], [$b, 4.0, '50']]);
        $buyerToken = $this->token($buyer);
        $adminToken = $this->adminToken();

        // The request of the buyer: revision 1 holds the lines asked for, at the catalogue prices of the day.
        $request = $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $cart->getId(), 'comment' => 'For the new shop'], $buyerToken, 'json');
        self::assertSame(201, $request->getStatusCode());
        $quoteId = $this->body($request)['id'];
        $first = array_column($this->frontRevision($quoteId, 1, $buyerToken)['items'], 'id');

        // The answer of the shop: A x12 at 90, B x4 at 45, C x5 at 20 added, 30 off, 15 of postage.
        $draft = $this->jsonRequest('PUT', '/api/admin/quotes/'.$quoteId.'/draft', [
            'lines' => [
                ['quoteItemId' => $first[0], 'productSaleElementsId' => $this->saleElementId($a), 'quantity' => 12, 'price' => '90'],
                ['quoteItemId' => $first[1], 'productSaleElementsId' => $this->saleElementId($b), 'quantity' => 4, 'price' => '45'],
                ['productSaleElementsId' => $this->saleElementId($c), 'quantity' => 5, 'price' => '20'],
            ],
            'discount' => '30',
            'postage' => '15',
            'deliveryModuleId' => $this->deliveryModuleId(),
            'validUntil' => (new \DateTimeImmutable('+15 days'))->format('Y-m-d'),
        ], $adminToken, 'json');
        self::assertSame(200, $draft->getStatusCode());
        $send = $this->jsonRequest('POST', '/api/admin/quotes/'.$quoteId.'/send', token: $adminToken, format: 'json');
        self::assertSame(200, $send->getStatusCode());
        self::assertSame('1620.000000', $this->body($send)['revisions'][1]['totalTaxed']);

        // The counter-proposal of the buyer: A at 85, the quantity as it is.
        $second = $this->lineIdsOf($this->body($send), 2);
        $counter = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quoteId.'/counter-offer', ['revision' => 2, 'lines' => [['quoteItemId' => $second[0], 'price' => '85']]], $buyerToken, 'json');
        self::assertSame(200, $counter->getStatusCode());
        self::assertSame('countered', $this->body($counter)['state']);

        // The shop answers again: A at 87, the rest as it was.
        $again = $this->jsonRequest('PUT', '/api/admin/quotes/'.$quoteId.'/draft', [
            'lines' => [
                ['quoteItemId' => $second[0], 'productSaleElementsId' => $this->saleElementId($a), 'quantity' => 12, 'price' => '87'],
                ['quoteItemId' => $second[1], 'productSaleElementsId' => $this->saleElementId($b), 'quantity' => 4, 'price' => '45'],
                ['quoteItemId' => $second[2], 'productSaleElementsId' => $this->saleElementId($c), 'quantity' => 5, 'price' => '20'],
            ],
            'discount' => '30',
            'postage' => '15',
            'deliveryModuleId' => $this->deliveryModuleId(),
            'validUntil' => (new \DateTimeImmutable('+15 days'))->format('Y-m-d'),
        ], $adminToken, 'json');
        self::assertSame(200, $again->getStatusCode());
        $resent = $this->jsonRequest('POST', '/api/admin/quotes/'.$quoteId.'/send', token: $adminToken, format: 'json');
        self::assertSame(200, $resent->getStatusCode());

        $read = $this->body($this->jsonRequest('GET', '/api/front/account/quotes/'.$quoteId, token: $buyerToken));
        self::assertSame('quoted', $read['state']);
        $history = $this->body($this->jsonRequest('GET', '/api/front/account/quotes/'.$quoteId.'/revisions', token: $buyerToken))['hydra:member'];
        self::assertSame([1, 2, 3], array_column($history, 'number'), 'The history holds three revisions.');
        self::assertSame('1576.800000', $history[2]['totalTaxed']);
        self::assertSame('87.000000', $this->frontRevision($quoteId, 3, $buyerToken)['items'][0]['price']);

        // The acceptance names the revision; the cart that comes back is the one the buyer pays.
        $accept = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quoteId.'/accept', ['revision' => 3], $buyerToken, 'json');
        self::assertSame(200, $accept->getStatusCode());
        $quoteCart = CartQuery::create()->findPk($this->body($accept)['id']);
        self::assertSame($quoteId, $quoteCart->getQuoteId());

        $order = $this->pay($quoteCart, $buyer);

        self::assertSame($quoteId, $order->getQuoteId());
        self::assertSame(30.0, (float) $order->getDiscount());
        self::assertSame(18.0, (float) $order->getPostage(), 'The postage of the quote, 15 with its tax of 3.');
        self::assertSame(1576.8, round($order->getTotalAmount(), 2), '(1324 + 15) x 1.2 - 30');

        $lines = [...OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find()];
        self::assertSame([87.0, 45.0, 20.0], array_map(static fn ($line): float => round((float) $line->getPrice(), 6), $lines), 'The negotiated prices, not those of the catalogue.');
        self::assertSame(
            [17.4, 9.0, 4.0],
            array_map(
                static fn ($line): float => round(array_sum(array_map(static fn ($tax): float => (float) $tax->getAmount(), [...OrderProductTaxQuery::create()->filterByOrderProductId($line->getId())->find()])), 6),
                $lines,
            ),
        );
        QuoteTableMap::clearInstancePool();
        $quote = QuoteQuery::create()->findPk($quoteId);
        self::assertSame('ordered', $quote->getState());
        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quoteId)->count());

        $ordered = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quoteId, token: $buyerToken);
        self::assertSame('ordered', $this->body($ordered)['state']);
        self::assertSame(409, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quoteId.'/accept', ['revision' => 3], $buyerToken, 'json')->getStatusCode());
        self::assertSame('transition_refused', $this->reason($this->jsonRequest('POST', '/api/front/account/quotes/'.$quoteId.'/reject', ['revision' => 3], $buyerToken, 'json')));
    }

    private function deliveryModuleId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId();
    }

    private function paymentModuleId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('Cheque')?->getId();
    }

    /**
     * The order the buyer places with the cart, the way the checkout does when the payment is chosen.
     */
    private function pay(Cart $cart, Customer $buyer): Order
    {
        $cart = CartQuery::create()->findPk($cart->getId());

        if (null === $cart->getAddressDeliveryId() || null === $cart->getAddressInvoiceId()) {
            $address = $this->fixtures->address($buyer, Country::getDefaultCountry());
            $delivery = new CartAddress();
            $delivery->copyFrom($address);
            $delivery->save($this->getPropelConnection());
            $invoice = new CartAddress();
            $invoice->copyFrom($address);
            $invoice->save($this->getPropelConnection());
            $cart->setAddressDeliveryId($delivery->getId())->setAddressInvoiceId($invoice->getId())->save($this->getPropelConnection());
        }

        $this->getService(SecurityContext::class)->setCustomerUser($buyer);

        $order = $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $cart->getDeliveryModuleId(),
            $this->paymentModuleId(),
            CurrencyQuery::create()->findPk($cart->getCurrencyId()),
        ))->placedOrder;
        OrderTableMap::clearInstancePool();

        return $order;
    }
}
