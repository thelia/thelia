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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Promotion\Coupon\Exception\CouponNoUsageLeftException;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Model\Coupon;
use Thelia\Model\Customer;
use Thelia\Model\Map\CouponTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderQuery;
use Thelia\Test\FixtureFactory;

/**
 * The coupons of a placed order are recorded on it after the checkout checked
 * them. Two buyers placing an order on the last usage at the same time both
 * passed that check: the usages left are read again under a lock on the coupon
 * row when the order records its coupons, and the order that comes second is
 * refused.
 */
final class CouponUsageAtPlacementTest extends ActionIntegrationPromotionTestCase
{
    /** Lock wait timeout (MariaDB, which also answers NOWAIT with it) and NOWAIT refusal (MySQL 8). */
    private const array LOCK_REFUSED_ERROR_CODES = [1205, 3572];

    private ?ConnectionInterface $secondConnection = null;

    private ?Coupon $committedCoupon = null;

    protected function setUp(): void
    {
        // Committed before the test transaction starts, or that transaction would
        // read the database as it was before the coupon existed.
        if ('testTheCouponRowIsLockedWhileTheOrderRecordsItsCoupons' === $this->name()) {
            self::bootKernel();
            $this->committedCoupon = $this->typedCoupon(new FixtureFactory($this->secondConnection()));
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        try {
            if (null !== $this->committedCoupon) {
                $this->secondConnection?->prepare('DELETE FROM coupon WHERE id = :id')->execute([':id' => $this->committedCoupon->getId()]);
            }
        } finally {
            $this->committedCoupon = null;
            $this->secondConnection = null;
        }
    }

    public function testAnOrderRecordingTheLastUsageAnotherOrderTookMeanwhileIsRefused(): void
    {
        $coupon = $this->typedCoupon();
        $customer = $this->checkoutWith($coupon);
        $order = $this->factory->order($customer);

        // The other buyer records its order between the check of this checkout and
        // the recording of this order.
        $this->factory->orderCoupon($this->factory->order(), $coupon);

        try {
            $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);
            self::fail('The order was recorded with a usage the coupon no longer had.');
        } catch (CouponNoUsageLeftException) {
        }

        self::assertSame(0, OrderCouponQuery::create()->filterByOrderId($order->getId())->count());
        self::assertTrue(OrderQuery::create()->findPk($order->getId())?->isCancelled(), 'The refused order is cancelled.');
    }

    public function testTheCouponRowIsLockedWhileTheOrderRecordsItsCoupons(): void
    {
        $coupon = $this->committedCoupon;
        self::assertNotNull($coupon);
        $customer = $this->checkoutWith($coupon);
        $order = $this->factory->order($customer);

        // The test transaction stays open: whatever the order locked is still locked.
        $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);

        self::assertSame(1, OrderCouponQuery::create()->filterByOrderId($order->getId())->count());
        self::assertFalse($this->aSecondRequestCanLock($coupon), 'Another order could read the usages left while this one was recording its coupon.');
    }

    private function checkoutWith(Coupon $coupon): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());
        $this->session()->setConsumedCoupons([$coupon->getCode()]);

        $couponManager = $this->getService(CouponManager::class);
        $couponManager->invalidateCurrentCoupons();
        self::assertCount(1, $couponManager->getCouponsKept(), 'The checkout applies the coupon.');

        return $customer;
    }

    private function typedCoupon(?FixtureFactory $factory = null): Coupon
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);

        return ($factory ?? $this->factory)->coupon([
            'code' => uniqid('LAST-USAGE-'),
            'maxUsage' => 1,
            'conditions' => $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions),
        ]);
    }

    private function aSecondRequestCanLock(Coupon $coupon): bool
    {
        try {
            $this->secondConnection()
                ->prepare('SELECT id FROM coupon WHERE id = :id FOR UPDATE NOWAIT')
                ->execute([':id' => $coupon->getId()]);
        } catch (\PDOException $refused) {
            if (!\in_array($refused->errorInfo[1] ?? null, self::LOCK_REFUSED_ERROR_CODES, true)) {
                throw $refused;
            }

            return false;
        }

        return true;
    }

    /**
     * A connection of its own, opened from the configuration Propel already holds:
     * Propel hands out one connection per datasource, and the test transaction is
     * on it.
     */
    private function secondConnection(): ConnectionInterface
    {
        if (null !== $this->secondConnection) {
            return $this->secondConnection;
        }

        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(CouponTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::fail(\sprintf('A second connection is opened from a single-connection manager, the test database is served by %s.', get_debug_type($connectionManager)));
        }

        return $this->secondConnection = ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(CouponTableMap::DATABASE_NAME),
        );
    }
}
