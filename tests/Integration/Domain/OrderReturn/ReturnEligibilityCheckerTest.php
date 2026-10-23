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

namespace Thelia\Tests\Integration\Domain\OrderReturn;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\OrderReturnComposer;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The one gate every entry point (Flexy, the front API, the back-office) goes
 * through to decide whether a return may be opened, and for which quantity.
 */
final class ReturnEligibilityCheckerTest extends IntegrationTestCase
{
    use SecondConnectionTrait;

    private ReturnEligibilityChecker $checker;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deleteTheLinesAKilledTestLeft();
        $this->checker = $this->getService(ReturnEligibilityChecker::class);
        $this->factory = $this->createFixtureFactory();

        ConfigQuery::write('order_return_enabled', '1');
        ConfigQuery::write('order_return_window_days', '14');
    }

    protected function tearDown(): void
    {
        // ConfigQuery keeps a static cache the transaction rollback cannot reach.
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        try {
            parent::tearDown();
        } finally {
            // Once the test transaction is rolled back, which is what releases the
            // lock the test took on them, and even when the teardown fails.
            $this->deleteTheCommittedLines();
        }
    }

    public function testAnEligibleOrderCanBeOpened(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();

        $this->checker->assertOrderReturnable($order, $customer);

        self::assertTrue($this->checker->isReturnable($order));
    }

    public function testTheDisabledFeatureBlocksOpening(): void
    {
        ConfigQuery::write('order_return_enabled', '0');
        [$order, $customer] = $this->paidOrderWithProduct();

        self::assertFalse($this->checker->isReturnable($order));

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertOrderReturnable($order, $customer);
    }

    public function testAnOrderOutsideTheWindowBlocksOpening(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct(placedDaysAgo: 30);

        self::assertFalse($this->checker->isWithinReturnWindow($order));
        self::assertFalse($this->checker->isReturnable($order));

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertOrderReturnable($order, $customer);
    }

    /**
     * The window runs from the day the goods left, not from the day the order was
     * placed: a customer who waited three weeks for a delivery has not spent their
     * retraction period waiting for it.
     */
    public function testTheWindowIsCountedFromTheDayTheOrderWasSent(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct(placedDaysAgo: 30);

        self::assertFalse(
            $this->checker->isWithinReturnWindow($order),
            'Guard: counted from the order date, this one is long closed.',
        );

        $this->shipmentRecordedDaysAgo($order, 2);

        self::assertTrue($this->checker->isWithinReturnWindow($order));
        $this->checker->assertOrderReturnable($order, $customer);
    }

    /**
     * An order sent again after coming back starts a new window, which is the
     * reading that favours the buyer.
     */
    public function testTheLastShipmentIsTheOneThatCounts(): void
    {
        [$order] = $this->paidOrderWithProduct(placedDaysAgo: 60);

        $this->shipmentRecordedDaysAgo($order, 50);
        $this->shipmentRecordedDaysAgo($order, 3);

        self::assertTrue($this->checker->isWithinReturnWindow($order));
    }

    public function testAShipmentOlderThanTheWindowClosesIt(): void
    {
        [$order] = $this->paidOrderWithProduct(placedDaysAgo: 1);

        self::assertTrue(
            $this->checker->isWithinReturnWindow($order),
            'Guard: counted from the order date, this one is wide open.',
        );

        $this->shipmentRecordedDaysAgo($order, 40);

        self::assertFalse($this->checker->isWithinReturnWindow($order));
    }

    /**
     * An order placed before the history existed has no shipment date on file. It
     * keeps the rule that applied to it until now, so no window closes or opens
     * because of the change.
     */
    public function testAnOrderWithNoRecordedShipmentFallsBackOnItsCreationDate(): void
    {
        [$recentOrder] = $this->paidOrderWithProduct(placedDaysAgo: 1);
        [$oldOrder] = $this->paidOrderWithProduct(placedDaysAgo: 30);

        self::assertTrue($this->checker->isWithinReturnWindow($recentOrder));
        self::assertFalse($this->checker->isWithinReturnWindow($oldOrder));
    }

    /**
     * The status a transition lands on lives inside the JSON payload, and a status
     * code that is the prefix of another one — or the same code sitting under "from"
     * rather than "to" — must not be taken for a shipment.
     */
    public function testATransitionAwayFromSentIsNotAShipment(): void
    {
        [$order] = $this->paidOrderWithProduct(placedDaysAgo: 30);

        $this->statusChangeRecordedDaysAgo($order, 1, OrderStatus::CODE_SENT, OrderStatus::CODE_REFUNDED);

        self::assertFalse(
            $this->checker->isWithinReturnWindow($order),
            'Leaving the sent status is not being sent.',
        );
    }

    public function testAnUnpaidOrderIsNotWithinTheWindow(): void
    {
        [$order] = $this->paidOrderWithProduct(statusCode: OrderStatus::CODE_NOT_PAID);

        self::assertFalse($this->checker->isWithinReturnWindow($order));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedOrderStatuses(): iterable
    {
        yield 'cancelled' => [OrderStatus::CODE_CANCELED, 'This order has been cancelled and can no longer be returned.'];
        yield 'refunded' => [OrderStatus::CODE_REFUNDED, 'This order has already been refunded and can no longer be returned.'];
        yield 'unpaid' => [OrderStatus::CODE_NOT_PAID, 'This order has not been paid yet and cannot be returned.'];
    }

    /**
     * A cancelled, refunded or unpaid order gets its own refusal, distinct from
     * "the window has closed": the customer did not miss a deadline.
     */
    #[DataProvider('refusedOrderStatuses')]
    public function testAnOrderThatCannotBeReturnedIsRefusedWithAnExplicitMessage(string $statusCode, string $message): void
    {
        [$order, $customer] = $this->paidOrderWithProduct(statusCode: $statusCode);

        $this->expectException(ReturnNotAllowedException::class);
        $this->expectExceptionMessage($message);
        $this->checker->assertOrderReturnable($order, $customer);
    }

    /**
     * The explicit refusals do not override the shop: a status it lists as one
     * returns open on is accepted, whatever the status is called.
     */
    #[DataProvider('refusedOrderStatuses')]
    public function testAStatusTheShopOpensReturnsOnIsNotRefusedWithAnExplicitMessage(string $statusCode): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ORDER_STATUSES_CONFIG_KEY, $statusCode);
        [$order, $customer] = $this->paidOrderWithProduct(statusCode: $statusCode);

        $this->checker->assertOrderReturnable($order, $customer);

        self::assertTrue($this->checker->isReturnable($order));
    }

    /**
     * The ownership answer comes first: an order that is not the caller's says
     * nothing of its status.
     */
    public function testAForeignCancelledOrderIsRefusedAsAForeignOrder(): void
    {
        [$order] = $this->paidOrderWithProduct(statusCode: OrderStatus::CODE_CANCELED);
        $other = $this->factory->customer($this->factory->customerTitle());

        $this->expectException(ReturnNotAllowedException::class);
        $this->expectExceptionMessage('This order cannot be used for a return.');
        $this->checker->assertOrderReturnable($order, $other);
    }

    public function testAForeignOrderCannotBeOpenedByAnotherCustomer(): void
    {
        [$order] = $this->paidOrderWithProduct();
        $intruder = $this->factory->customer($this->factory->customerTitle());

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertOrderReturnable($order, $intruder);
    }

    public function testAVirtualProductIsNeverReturnable(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $virtual = $this->orderProduct($order, quantity: 1.0, virtual: true);

        self::assertFalse($this->checker->isProductReturnable($virtual));

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $virtual, 1.0);
    }

    public function testAQuantityAboveTheOrderedOneIsRejected(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 2.0);

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $line, 3.0);
    }

    public function testOpenReturnsConsumeTheReturnableQuantity(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 3.0);

        self::assertSame(3.0, $this->checker->remainingReturnableQuantity($line));

        $this->openReturn($order, $customer, $line, 2.0, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame(1.0, $this->checker->remainingReturnableQuantity($line));

        // One unit left: asking for two is refused.
        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $line, 2.0);
    }

    public function testARefusedReturnFreesItsQuantity(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 3.0);

        $this->openReturn($order, $customer, $line, 3.0, OrderReturnStatus::CODE_REFUSED);

        // A refused return holds nothing: the whole quantity is returnable again.
        self::assertSame(3.0, $this->checker->remainingReturnableQuantity($line));
    }

    /**
     * A quantity is a FLOAT - Thelia sells by weight and by length as well as
     * by the piece - so the remaining quantity is a subtraction of floats:
     * 0.7 ordered minus 0.3 already asked for is 0.39999999999999997, and the
     * customer asking for the 0.4 the shop displays used to be refused.
     */
    public function testTheRemainderOfAFloatQuantityIsStillReturnable(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->storedOrderProduct($this->orderProduct($order, quantity: 0.7));

        $this->openReturn($order, $customer, $line, 0.3, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame(0.4, $this->checker->remainingReturnableQuantity($line));

        $this->checker->assertReturnable($order, $customer, $line, 0.4);
    }

    /**
     * The tolerance the float subtraction needs must not become an extra unit
     * handed out on a line that is already entirely returned.
     */
    public function testAFullyReturnedFloatLineHasNothingLeft(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->storedOrderProduct($this->orderProduct($order, quantity: 0.7));

        $this->openReturn($order, $customer, $line, 0.3, OrderReturnStatus::CODE_REQUESTED);
        $this->openReturn($order, $customer, $line, 0.4, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame(0.0, $this->checker->remainingReturnableQuantity($line));

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $line, 0.1);
    }

    /**
     * A shop that takes back only what it has shipped lists the sent status: a
     * paid order still waiting for its parcel cannot be returned, through any of
     * the paths that ask this gate.
     */
    public function testOnlyTheListedOrderStatusesOpenAReturn(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ORDER_STATUSES_CONFIG_KEY, ' sent ,');
        [$paidOrder, $paidCustomer] = $this->paidOrderWithProduct();
        [$sentOrder, $sentCustomer] = $this->paidOrderWithProduct(statusCode: OrderStatus::CODE_SENT);

        self::assertSame([OrderStatus::CODE_SENT], $this->checker->openingStatusCodes());
        self::assertFalse($this->checker->isWithinReturnWindow($paidOrder));
        self::assertFalse($this->checker->isReturnable($paidOrder));
        self::assertTrue($this->checker->isWithinReturnWindow($sentOrder));
        $this->checker->assertOrderReturnable($sentOrder, $sentCustomer);

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertOrderReturnable($paidOrder, $paidCustomer);
    }

    /**
     * A listed status does not have to be a paid one: the list replaces the rule,
     * it does not narrow it.
     */
    public function testAListedStatusOpensAReturnEvenWhenItIsNotAPaidOne(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ORDER_STATUSES_CONFIG_KEY, OrderStatus::CODE_NOT_PAID);
        [$order] = $this->paidOrderWithProduct(statusCode: OrderStatus::CODE_NOT_PAID);

        self::assertTrue($this->checker->isWithinReturnWindow($order));
    }

    public function testAnEmptyListOpensAReturnOnAnyPaidStatus(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ORDER_STATUSES_CONFIG_KEY, '');
        [$paidOrder] = $this->paidOrderWithProduct();
        [$processingOrder] = $this->paidOrderWithProduct(statusCode: OrderStatus::CODE_PROCESSING);
        [$unpaidOrder] = $this->paidOrderWithProduct(statusCode: OrderStatus::CODE_NOT_PAID);

        self::assertSame([], $this->checker->openingStatusCodes());
        self::assertTrue($this->checker->isWithinReturnWindow($paidOrder));
        self::assertTrue($this->checker->isWithinReturnWindow($processingOrder));
        self::assertFalse($this->checker->isWithinReturnWindow($unpaidOrder));
    }

    /**
     * A shop that settles an exchange with a new parcel lets the goods it shipped
     * again be returned in turn: with the cumulative count off, the returns already
     * opened on a line hold nothing and each request is bounded by the ordered
     * quantity alone.
     */
    public function testEachRequestIsBoundedByTheOrderedQuantityAloneWhenTheCountIsNotCumulative(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY, '0');
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 2.0);

        $this->openReturn($order, $customer, $line, 2.0, OrderReturnStatus::CODE_SETTLED);
        $this->openReturn($order, $customer, $line, 1.0, OrderReturnStatus::CODE_REQUESTED);

        self::assertFalse($this->checker->isQuantityCumulative());
        self::assertSame(2.0, $this->checker->remainingReturnableQuantity($line));
        self::assertTrue($this->checker->isReturnable($order));
        $this->checker->assertReturnable($order, $customer, $line, 2.0);

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $line, 3.0);
    }

    /**
     * Turning the cumulative count off frees the earlier returns, not the request
     * being written: one ordered unit split over two lines of the same request is
     * still refused.
     */
    public function testASingleRequestStillCannotClaimMoreThanTheOrderedQuantity(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY, '0');
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 2.0);

        $this->openReturn($order, $customer, $line, 2.0, OrderReturnStatus::CODE_REQUESTED);

        $this->expectException(ReturnNotAllowedException::class);
        $this->getService(OrderReturnComposer::class)->priceRequestedLines($order, $customer, [
            ['order_product' => $line, 'quantity' => 1.5],
            ['order_product' => $line, 'quantity' => 1.0],
        ]);
    }

    public function testTheCountIsCumulativeByDefault(): void
    {
        self::assertSame('1', ConfigQuery::read(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY));
        self::assertTrue($this->checker->isQuantityCumulative());
    }

    public function testTheCountIsCumulativeOnAShopWithoutTheSetting(): void
    {
        ConfigQuery::create()->filterByName(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY)->delete($this->getPropelConnection());
        ConfigQuery::resetCache();

        self::assertTrue($this->checker->isQuantityCumulative());
    }

    public function testTheFeatureSwitchIsTheShopSetting(): void
    {
        self::assertTrue($this->checker->isFeatureEnabled());

        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '0');

        self::assertFalse($this->checker->isFeatureEnabled());
    }

    public function testTheOpeningStatusesAreAListWhateverSeparatorsSurroundThem(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ORDER_STATUSES_CONFIG_KEY, ', sent,, paid');

        self::assertSame([OrderStatus::CODE_SENT, OrderStatus::CODE_PAID], $this->checker->openingStatusCodes());
    }

    public function testEveryLineStillReturnableIsOfferedWithWhatIsLeftOfIt(): void
    {
        [$order, $customer, $untouched] = $this->paidOrderWithProduct();
        $fullyReturned = $this->orderProduct($order, quantity: 2.0);
        $partlyReturned = $this->orderProduct($order, quantity: 3.0);

        $this->openReturn($order, $customer, $partlyReturned, 1.0, OrderReturnStatus::CODE_REQUESTED);
        $this->openReturn($order, $customer, $fullyReturned, 2.0, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame(
            [[$untouched->getId(), 1.0], [$partlyReturned->getId(), 2.0]],
            array_map(
                static fn (array $line): array => [$line['order_product']->getId(), $line['remaining']],
                $this->checker->returnableLines($order),
            ),
        );
    }

    public function testAnOrderWhoseLinesAreAllReturnedIsNoLongerReturnable(): void
    {
        [$order, $customer, $line] = $this->paidOrderWithProduct();

        $this->openReturn($order, $customer, $line, 1.0, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame([], $this->checker->returnableLines($order));
        self::assertFalse($this->checker->isReturnable($order));
    }

    /**
     * Every status but the refused and expired ones keeps the quantity it was
     * opened for: a return accepted, received or settled has not given it back.
     */
    public function testEveryReturnStillOpenHoldsItsQuantity(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 7.0);

        foreach ([
            OrderReturnStatus::CODE_REQUESTED,
            OrderReturnStatus::CODE_INFO_AWAITED,
            OrderReturnStatus::CODE_ACCEPTED,
            OrderReturnStatus::CODE_RECEIVED,
            OrderReturnStatus::CODE_SETTLED,
            OrderReturnStatus::CODE_REFUSED,
            OrderReturnStatus::CODE_EXPIRED,
        ] as $statusCode) {
            $this->openReturn($order, $customer, $line, 1.0, $statusCode);
        }

        self::assertSame(2.0, $this->checker->remainingReturnableQuantity($line));
    }

    public function testTheReturnBeingEditedDoesNotHoldItsOwnQuantity(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 3.0);

        $edited = $this->openReturn($order, $customer, $line, 2.0, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame(3.0, $this->checker->remainingReturnableQuantity($line, (int) $edited->getId()));
        $this->checker->assertReturnable($order, $customer, $line, 3.0, (int) $edited->getId());
    }

    public function testAZeroQuantityIsRefused(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 2.0);

        $this->expectException(ReturnNotAllowedException::class);
        $this->expectExceptionMessage('The returned quantity must be positive.');
        $this->checker->assertReturnable($order, $customer, $line, 0.0);
    }

    public function testAQuantitySoldByWeightIsReturnableToTheGramWhenTheCountIsNotCumulative(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY, '0');
        [$order] = $this->paidOrderWithProduct();
        $line = $this->storedOrderProduct($this->orderProduct($order, quantity: 2.5));

        self::assertSame(2.5, $this->checker->remainingReturnableQuantity($line));
    }

    public function testALineOrderedAtZeroHasNothingToReturnWhenTheCountIsNotCumulative(): void
    {
        // Pins the 0.0 fallback itself; `>` and `>=` give the same answer at zero.
        ConfigQuery::write(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY, '0');
        [$order] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 0.0);

        self::assertSame(0.0, $this->checker->remainingReturnableQuantity($line));
    }

    /**
     * Two requests for the same line must not both read the quantity still
     * returnable before either has written its return. The line is committed
     * from a connection of its own, so that this second connection, standing
     * for the second request, sees it and can try to take it.
     */
    public function testALockedLineCannotBeTakenByASecondRequest(): void
    {
        [$order] = $this->paidOrderWithProduct();
        $line = $this->committedOrderProduct($order, quantity: 2.0);

        self::assertTrue($this->aSecondRequestCanTakeTheLine($line), 'Guard: nothing holds the line yet.');

        $this->checker->lockLine($line);

        self::assertFalse($this->aSecondRequestCanTakeTheLine($line), 'A second request took a line the first one holds.');
    }

    public function testAReturnThatDoesNotCarryThePostageLeavesItToAnother(): void
    {
        [$order, $customer, $line] = $this->paidOrderWithProduct();

        $this->openReturn($order, $customer, $line, 1.0, OrderReturnStatus::CODE_REQUESTED);

        $this->checker->assertPostageNotAlreadyReturned($order);
        $this->addToAssertionCount(1);
    }

    public function testARefusedOrExpiredReturnGivesThePostageBack(): void
    {
        [$order, $customer, $line] = $this->paidOrderWithProduct();

        $this->openReturn($order, $customer, $line, 1.0, OrderReturnStatus::CODE_REFUSED, includePostage: true);
        $this->openReturn($order, $customer, $line, 1.0, OrderReturnStatus::CODE_EXPIRED, includePostage: true);

        $this->checker->assertPostageNotAlreadyReturned($order);
        $this->addToAssertionCount(1);
    }

    public function testASettledReturnStillHoldsThePostage(): void
    {
        [$order, $customer, $line] = $this->paidOrderWithProduct();

        $this->openReturn($order, $customer, $line, 1.0, OrderReturnStatus::CODE_SETTLED, includePostage: true);

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertPostageNotAlreadyReturned($order);
    }

    public function testTheReturnBeingEditedDoesNotHoldItsOwnPostage(): void
    {
        [$order, $customer, $line] = $this->paidOrderWithProduct();

        $edited = $this->openReturn($order, $customer, $line, 1.0, OrderReturnStatus::CODE_REQUESTED, includePostage: true);

        $this->checker->assertPostageNotAlreadyReturned($order, (int) $edited->getId());

        $this->expectException(ReturnNotAllowedException::class);
        $this->expectExceptionMessage('The postage of this order is already included in another return.');
        $this->checker->assertPostageNotAlreadyReturned($order);
    }

    private function shipmentRecordedDaysAgo(Order $order, int $days): void
    {
        $this->statusChangeRecordedDaysAgo($order, $days, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT);
    }

    private function statusChangeRecordedDaysAgo(
        Order $order,
        int $days,
        string $fromStatusCode,
        string $toStatusCode,
    ): void {
        $entry = new OrderHistory();
        $entry
            ->setOrderId($order->getId())
            ->setEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->setActorType(OrderHistoryActorType::SYSTEM->value)
            ->setPayload(json_encode(['from' => $fromStatusCode, 'to' => $toStatusCode], \JSON_THROW_ON_ERROR))
            ->setVisibleToCustomer(0)
            ->save($this->getPropelConnection());

        // The timestampable behavior stamps created_at on insert; a date of its own
        // survives only the save that follows.
        $entry
            ->setCreatedAt(new \DateTime(\sprintf('-%d days', $days)))
            ->save($this->getPropelConnection());
    }

    /**
     * @return array{Order, Customer, OrderProductModel}
     */
    private function paidOrderWithProduct(
        string $statusCode = OrderStatus::CODE_PAID,
        int $placedDaysAgo = 1,
    ): array {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => $statusCode]);

        $order->setCreatedAt(new \DateTime(\sprintf('-%d days', $placedDaysAgo)));
        $order->save($this->getPropelConnection());

        $line = $this->orderProduct($order, quantity: 1.0);

        return [$order, $customer, $line];
    }

    private function orderProduct(Order $order, float $quantity, bool $virtual = false): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(1)
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual($virtual ? 1 : 0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }

    /**
     * The same line read back from the database, so that the quantity carries
     * the precision the FLOAT column actually stores and not the double the
     * fixture set.
     */
    private function storedOrderProduct(OrderProductModel $orderProduct): OrderProductModel
    {
        $stored = OrderProductQuery::create()->findPk($orderProduct->getId(), $this->getPropelConnection());

        self::assertNotNull($stored);

        return $stored;
    }

    private function openReturn(
        Order $order,
        Customer $customer,
        OrderProductModel $orderProduct,
        float $quantity,
        string $statusCode,
        bool $includePostage = false,
    ): OrderReturn {
        $status = OrderReturnStatusQuery::create()->findOneByCode($statusCode);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($status)
            ->setIncludePostage($includePostage);
        $return->save($this->getPropelConnection());

        (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setQuantity($quantity)
            ->save($this->getPropelConnection());

        return $return;
    }
}
