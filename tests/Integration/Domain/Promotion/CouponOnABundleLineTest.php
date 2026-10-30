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

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\PlacesBundleOrders;

/**
 * A coupon on the total of the cart applies to the line of a pack, at the price of that line:
 * the order keeps the discount and the lines of the components stay at zero.
 *
 * The pack of the plan (bike, helmet, lock) is ordered once at 600 before tax; the coupon is on the taxed total, 720, hence 72.
 */
final class CouponOnABundleLineTest extends ActionIntegrationPromotionTestCase
{
    use BuildsBundles;
    use PlacesBundleOrders;

    private const PACK_PRICE = '600.000000';

    public function testACouponOnTheTotalOfTheCartTakesItsPercentageOfTheLineOfThePack(): void
    {
        [$cart] = $this->cartWithAPackAndACoupon();

        $cart->reload();

        self::assertEqualsWithDelta(72.0, (float) $cart->getDiscount(), 0.005, 'Ten percent of the pack line, 600 taxed at the 20 % of the default rule.');
    }

    public function testACouponOnTheTotalOfTheCartCountsThePackLineAndThePlainLineAndNoComponent(): void
    {
        [$cart] = $this->cartWithAPackAndACoupon(simpleLinePrice: '50.000000');

        $cart->reload();

        // 600 + 50 before tax, 720 + 60 taxed: a component counted at its catalogue price would raise it.
        self::assertEqualsWithDelta(78.0, (float) $cart->getDiscount(), 0.005, 'Ten percent of the pack line and the plain line, 780 taxed.');
    }

    public function testTheOrderKeepsTheDiscountAndTheComponentsStayAtZero(): void
    {
        [$cart, $customer] = $this->cartWithAPackAndACoupon();

        $order = $this->placeCouponOrder($customer, $cart);

        self::assertEqualsWithDelta(72.0, (float) $order->getDiscount(), 0.005);
        self::assertCount(1, OrderCouponQuery::create()->filterByOrderId($order->getId())->find());

        $lines = $this->linesOf($order);
        self::assertCount(4, $lines, 'The pack line and its three components.');

        foreach ($lines as $line) {
            if ($line->isBundleLine()) {
                self::assertEqualsWithDelta(600.0, (float) $line->getPrice(), 0.005, 'The pack line keeps its own price: the discount is on the order.');

                continue;
            }

            self::assertTrue($line->isBundleComponentLine());
            self::assertSame(0.0, (float) $line->getPrice(), 'A component line stays at zero.');
            self::assertSame(0.0, (float) $line->getPromoPrice());
        }
    }

    /**
     * @return array{0: Cart, 1: Customer}
     */
    private function cartWithAPackAndACoupon(?string $simpleLinePrice = null): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $cart = $this->newEmptyCart();
        $cart->save();
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2', (int) $example['lock']->getId() => '1'], 1.0, self::PACK_PRICE, self::PACK_PRICE);

        $coupon = $this->factory->coupon([
            'type' => 'thelia.coupon.type.remove_x_percent',
            'effects' => ['percentage' => 10.0],
            'code' => 'PACK10-'.uniqid(),
            'conditions' => $this->alwaysMatching(),
        ]);

        if (null !== $simpleLinePrice) {
            $this->addSimpleLine($cart, $this->product(), 1.0, $simpleLinePrice);
        }

        $cart->clearCartItems();
        $this->session()->setConsumedCoupons([$coupon->getCode()]);
        $this->recomputeDiscount();

        return [$cart, $customer];
    }

    private function alwaysMatching(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }

    private function placeCouponOrder(Customer $customer, Cart $cart): Order
    {
        $sessionOrder = $this->session()->getOrder();
        $sessionOrder
            ->setDeliveryOrderAddressId($this->factory->orderAddress()->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress()->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        $order = $this->getService(OrderFacade::class)->createOrder(
            $this->dispatcher,
            $sessionOrder,
            $this->session()->getCurrency(),
            $this->factory->lang(),
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
        );

        $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);

        return $order;
    }
}
