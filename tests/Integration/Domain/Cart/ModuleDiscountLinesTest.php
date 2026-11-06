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

namespace Thelia\Tests\Integration\Domain\Cart;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Action\Coupon as CouponAction;
use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Order\OrderDiscountLineEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Discount\CartDiscountLine;
use Thelia\Domain\Cart\Discount\CartDiscountLineProviderInterface;
use Thelia\Domain\Cart\Discount\CartDiscountLines;
use Thelia\Domain\Cart\Service\CartDiscountReconciler;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\OrderDiscountLineRecorder;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderDiscountLineQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * A module takes an amount off the cart under its own name (a store credit): the cart
 * lists it beside the promotions, adds it to its discount, and the order freezes it.
 *
 * The container is built at boot with the providers of the installed modules: the
 * services under test are rebuilt from the container's own, with a provider of the test.
 */
final class ModuleDiscountLinesTest extends ActionIntegrationPromotionTestCase
{
    /** @var list<CartDiscountLine> */
    private array $moduleLines = [];

    private AttributeAccessService $attributeAccess;

    private CouponAction $couponAction;

    private OrderFacade $orderFacade;

    private CartDiscountLines $lines;

    /** What the order announces its discount lines with: the modules listen to the kernel's. */
    private EventDispatcher $lineDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $test = $this;
        $provider = new class($test) implements CartDiscountLineProviderInterface {
            public function __construct(private readonly ModuleDiscountLinesTest $test)
            {
            }

            public function discountLinesFor(Cart $cart): iterable
            {
                return $this->test->moduleLines();
            }
        };

        $lines = $this->lines = new CartDiscountLines([$provider]);

        AttributeAccessService::clearCache();
        $this->attributeAccess = self::rebuilt($this->getService(AttributeAccessService::class), ['cartDiscountLines' => $lines]);
        $this->couponAction = self::rebuilt($this->getService(CouponAction::class), ['cartDiscountLines' => $lines]);
        $this->lineDispatcher = new EventDispatcher();
        $this->orderFacade = self::rebuilt(
            $this->getService(OrderFacade::class),
            ['orderDiscountLineRecorder' => new OrderDiscountLineRecorder($lines, $this->lineDispatcher)],
        );
    }

    /**
     * The same service, built with the collaborators of the container's one but those given.
     *
     * @template T of object
     *
     * @param T                    $real
     * @param array<string, mixed> $replaced
     *
     * @return T
     */
    private static function rebuilt(object $real, array $replaced): object
    {
        $collaborators = (fn (): array => get_object_vars($this))->call($real);

        return new ($real::class)(...array_merge($collaborators, $replaced));
    }

    /**
     * What a cart change ends with, through the action that knows the provider of the test.
     */
    private function priceTheDiscount(): void
    {
        $this->couponAction->updateOrderDiscount(new Event(), TheliaEvents::CART_DISCOUNT_LINES_CHANGED, $this->dispatcher);
        AttributeAccessService::clearCache();
    }

    /**
     * @return list<CartDiscountLine>
     */
    public function moduleLines(): array
    {
        return $this->moduleLines;
    }

    public function testAModuleLineIsListedBesideAPromotionAndAddedToTheDiscount(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->atLeastOneLine(), title: 'Five euros off');
        $this->moduleLines = [new CartDiscountLine('TestCredit.balance', 'Store credit', 3.0)];

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));
        $this->priceTheDiscount();

        self::assertSame(8.0, (float) $this->reloaded($cart)->getDiscount(), 'The cart discount is the promotion plus the line of the module.');

        $discounts = $this->attributeAccess->attributeCart('discounts');

        self::assertSame(['Five euros off', 'Store credit'], array_column($discounts, 'label'));
        self::assertSame([5.0, 3.0], array_column($discounts, 'taxed_amount'));
        self::assertSame($this->attributeAccess->attributeCart('taxed_discount'), array_sum(array_column($discounts, 'taxed_amount')));
        self::assertEqualsWithDelta($this->attributeAccess->attributeCart('discount'), array_sum(array_column($discounts, 'amount')), 0.001);
    }

    public function testAModuleLineMakesTheDiscountWithNoPromotion(): void
    {
        $this->moduleLines = [new CartDiscountLine('TestCredit.balance', 'Store credit', 4.0)];

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));
        $this->priceTheDiscount();

        self::assertSame(4.0, (float) $this->reloaded($cart)->getDiscount());
        self::assertSame(['Store credit'], array_column($this->attributeAccess->attributeCart('discounts'), 'label'));
        self::assertSame([4.0], array_column($this->attributeAccess->attributeCart('discounts'), 'taxed_amount'));
    }

    /**
     * Together they would take off more than the goods are worth: the discount stops at the
     * goods, the line of the module is served whole and the promotion takes what is left.
     */
    public function testTheModuleLinesAreServedFirstWhenTheDiscountReachesTheGoods(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->atLeastOneLine(), title: 'Five euros off');

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 10.0]));
        $this->priceTheDiscount();
        $goods = round((float) $this->attributeAccess->attributeCart('total_taxed_price_without_discount'), 2);

        $this->moduleLines = [new CartDiscountLine('TestCredit.balance', 'Store credit', $goods - 1.0)];
        $this->priceTheDiscount();

        self::assertSame($goods, (float) $this->reloaded($cart)->getDiscount());
        self::assertSame(
            ['Five euros off' => 1.0, 'Store credit' => $goods - 1.0],
            array_column($this->attributeAccess->attributeCart('discounts'), 'taxed_amount', 'label'),
        );
    }

    public function testAModuleAsksForTheDiscountToBePricedAgainThroughAnEventOfItsOwn(): void
    {
        self::assertSame(['updateOrderDiscount', 10], CouponAction::getSubscribedEvents()[TheliaEvents::CART_DISCOUNT_LINES_CHANGED] ?? null);
    }

    public function testALineThatChangesWithoutTheCartPricesTheDiscountAgain(): void
    {
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));
        $this->priceTheDiscount();

        self::assertSame(0.0, (float) $this->reloaded($cart)->getDiscount(), 'Control: nothing taken off yet.');

        $this->moduleLines = [new CartDiscountLine('TestCredit.balance', 'Store credit', 6.5)];
        $this->priceTheDiscount();

        self::assertSame(6.5, (float) $this->reloaded($cart)->getDiscount());

        $this->moduleLines = [];
        $this->priceTheDiscount();

        self::assertSame(0.0, (float) $this->reloaded($cart)->getDiscount(), 'A line the module withdrew leaves nothing in the discount.');
    }

    public function testThePlacedOrderFreezesTheLinesAndKeepsTheTotals(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->atLeastOneLine(), title: 'Five euros off');
        $this->moduleLines = [new CartDiscountLine('TestCredit.balance', 'Store credit', 3.0)];

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));
        $this->priceTheDiscount();
        $cart = $this->reloaded($cart);

        $order = $this->placeOrder($cart);

        self::assertSame((float) $cart->getDiscount(), (float) $order->getDiscount(), 'The order keeps the discount of its cart.');

        $this->moduleLines = [new CartDiscountLine('TestCredit.balance', 'Store credit', 50.0)];

        $rows = OrderDiscountLineQuery::create()->filterByOrderId($order->getId())->find();

        self::assertCount(1, $rows);
        self::assertSame('TestCredit.balance', $rows[0]->getLineKey());
        self::assertSame('Store credit', $rows[0]->getLabel());
        self::assertSame(3.0, (float) $rows[0]->getAmount(), 'What the module declared when the order was placed, not what it says now.');
    }

    /**
     * The module charges the balance where the line is written: in the transaction of the
     * order, with its connection, the row already readable there.
     */
    public function testEachLineWrittenIsAnnouncedInTheTransactionOfTheOrder(): void
    {
        $this->moduleLines = [
            new CartDiscountLine('TestCredit.balance', 'Store credit', 3.0),
            new CartDiscountLine('TestGift.card', 'Gift card', 2.0),
        ];

        /** @var list<array{key: string, amount: float, orderId: int|null, inTransaction: bool, readBack: float}> $announced */
        $announced = [];
        $this->lineDispatcher->addListener(
            TheliaEvents::ORDER_DISCOUNT_LINE_RECORD,
            static function (OrderDiscountLineEvent $event) use (&$announced): void {
                $connection = $event->getConnection();
                $readBack = OrderDiscountLineQuery::create()->findPk($event->getLine()->getId(), $connection);

                $announced[] = [
                    'key' => $event->getLine()->getLineKey(),
                    'amount' => (float) $event->getLine()->getAmount(),
                    'orderId' => $event->getOrder()->getId(),
                    'inTransaction' => $connection->inTransaction(),
                    'readBack' => (float) $readBack?->getAmount(),
                ];
            },
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));
        $this->priceTheDiscount();

        $order = $this->placeOrder($this->reloaded($cart));

        self::assertSame(
            [
                ['key' => 'TestCredit.balance', 'amount' => 3.0, 'orderId' => $order->getId(), 'inTransaction' => true, 'readBack' => 3.0],
                ['key' => 'TestGift.card', 'amount' => 2.0, 'orderId' => $order->getId(), 'inTransaction' => true, 'readBack' => 2.0],
            ],
            $announced,
            'One announcement per row written, with what the line was granted.',
        );
    }

    /**
     * The first line takes the whole worth of the goods: the second is granted nothing, so
     * nothing is charged for it.
     */
    public function testALineGrantedNothingIsNotAnnounced(): void
    {
        $this->moduleLines = [
            new CartDiscountLine('TestCredit.balance', 'Store credit', 12.0),
            new CartDiscountLine('TestGift.card', 'Gift card', 5.0),
        ];

        /** @var list<string> $announced */
        $announced = [];
        $this->lineDispatcher->addListener(
            TheliaEvents::ORDER_DISCOUNT_LINE_RECORD,
            static function (OrderDiscountLineEvent $event) use (&$announced): void {
                $announced[] = $event->getLine()->getLineKey();
            },
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 10.0]));
        $this->priceTheDiscount();
        $this->placeOrder($this->reloaded($cart));

        self::assertSame(['TestCredit.balance'], $announced);
    }

    /**
     * A balance that no longer covers the line refuses the order: the refusal reaches the
     * buyer, and the transaction of the order is rolled back (what the rollback undoes is
     * not visible inside the transaction the test case wraps around each test).
     */
    public function testAModuleThatRefusesItsLineRefusesTheOrder(): void
    {
        $this->moduleLines = [new CartDiscountLine('TestCredit.balance', 'Store credit', 3.0)];

        $refusal = new \RuntimeException('The balance no longer covers the line.');
        $this->lineDispatcher->addListener(
            TheliaEvents::ORDER_DISCOUNT_LINE_RECORD,
            static function () use ($refusal): void {
                throw $refusal;
            },
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));
        $this->priceTheDiscount();

        $this->expectExceptionObject($refusal);

        $this->placeOrder($this->reloaded($cart));
    }

    /**
     * A cart no session holds (the front API) is priced from the cart alone: the lines of the
     * modules are read from it, as the automatic promotions are.
     */
    public function testACartNoSessionHoldsIsPricedWithTheLinesOfTheModules(): void
    {
        $this->moduleLines = [new CartDiscountLine('TestCredit.balance', 'Store credit', 4.0)];

        $customer = $this->factory->customer($this->factory->customerTitle());
        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $this->product(['basePrice' => 100.0]));

        $reconciler = self::rebuilt($this->getService(CartDiscountReconciler::class), ['cartDiscountLines' => $this->lines]);

        self::assertFalse($reconciler->discountIsThePromotionsOwn($cart, $customer), 'Control: the stored discount misses the line.');

        $reconciler->reconcile($cart, $customer, true);

        self::assertSame(4.0, (float) $this->reloaded($cart)->getDiscount());
        self::assertTrue($reconciler->discountIsThePromotionsOwn($this->reloaded($cart), $customer));
    }

    public function testAnOrderWithNoModuleLineHasNoRow(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->atLeastOneLine());

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));
        $this->priceTheDiscount();

        $order = $this->placeOrder($this->reloaded($cart));

        self::assertSame(0, OrderDiscountLineQuery::create()->filterByOrderId($order->getId())->count());
    }

    private function atLeastOneLine(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => 1],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }

    private function reloaded(Cart $cart): Cart
    {
        \Thelia\Model\Map\CartTableMap::clearInstancePool();

        $reloaded = CartQuery::create()->findPk($cart->getId());
        self::assertInstanceOf(Cart::class, $reloaded);

        return $reloaded;
    }

    private function placeOrder(Cart $cart): Order
    {
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($deliveryModule);
        self::assertNotNull($paymentModule);

        $country = $this->factory->country();

        $sessionOrder = (new Order())
            ->setDeliveryOrderAddressId($this->factory->orderAddress($country)->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress($country)->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        return $this->orderFacade->createOrder(
            $this->dispatcher,
            $sessionOrder,
            $this->factory->currency(),
            $this->factory->lang(),
            $cart,
            $this->factory->customer($this->factory->customerTitle()),
            useOrderDefinedAddresses: true,
            recordConsentAnswers: false,
        );
    }
}
