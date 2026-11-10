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

namespace Thelia\Tests\Integration\Domain\Subscription;

use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Thelia\Domain\Subscription\Enum\PeriodUnit;
use Thelia\Domain\Subscription\SubscriptionFactory;
use Thelia\Domain\Subscription\SubscriptionInstallmentBiller;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionOrderQuery;
use Thelia\Model\SubscriptionPlan;
use Thelia\Model\SubscriptionPlanPrice;
use Thelia\Model\SubscriptionQuery;
use Thelia\Module\BaseModule;
use Thelia\Tests\Support\Delivery\FixedPostageDeliveryModule;
use Thelia\Tests\Support\Payment\RecurringTestPaymentModule;
use Thelia\Tests\Support\Payment\UsesRecurringTestPaymentModule;

/**
 * A shop that sells a product by subscription: a recurring payment module, a carrier with a
 * fixed postage, a product with its stock, a monthly plan, a customer with a stored method,
 * and a clock stopped at a known moment that the test moves forward.
 *
 * For an ActionIntegrationTestCase: setUpTheSubscriptionShop() in setUp(),
 * tearDownTheSubscriptionShop() in tearDown().
 */
trait SubscriptionScenario
{
    use UsesRecurringTestPaymentModule;

    private MockClock $clock;

    private ?ClockInterface $clockOfTheShop = null;

    private Module $recurringModule;

    private Module $carrier;

    private function setUpTheSubscriptionShop(string $now = '2028-01-31 10:00:00'): void
    {
        // In the timezone of PHP, as the clock of the shop is.
        $this->clock = new MockClock($now, date_default_timezone_get());
        $this->clockOfTheShop = Clock::get();
        Clock::set($this->clock);

        $container = static::getContainer();
        $this->recurringModule = $this->installTheRecurringTestPaymentModule($container, $this->getPropelConnection());

        $carrier = ModuleQuery::create()->findOneByCode(FixedPostageDeliveryModule::MODULE_CODE);

        if (null === $carrier) {
            $carrier = (new Module())
                ->setCode(FixedPostageDeliveryModule::MODULE_CODE)
                ->setFullNamespace(FixedPostageDeliveryModule::class)
                ->setVersion('1.0.0')
                ->setType(BaseModule::DELIVERY_MODULE_TYPE)
                ->setCategory('delivery')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $carrier->save($this->getPropelConnection());
        }

        $container->set('module.'.FixedPostageDeliveryModule::MODULE_CODE, new FixedPostageDeliveryModule());
        $this->carrier = $carrier;
    }

    private function tearDownTheSubscriptionShop(): void
    {
        if (null !== $this->clockOfTheShop) {
            Clock::set($this->clockOfTheShop);
        }

        $this->uninstallTheRecurringTestPaymentModule(static::getContainer());
        static::getContainer()->set('module.'.FixedPostageDeliveryModule::MODULE_CODE, null);
    }

    private function moveTo(string $moment): void
    {
        $this->clock->modify($moment);
    }

    private function aProduct(int $stock = 10, bool $virtual = false): Product
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => $stock, 'basePrice' => 10.0],
        );

        if ($virtual) {
            $product->setVirtual(1)->save($this->getPropelConnection());
        }

        return $product;
    }

    private function saleElementsOf(Product $product): ProductSaleElements
    {
        $saleElements = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($saleElements);

        return $saleElements;
    }

    /**
     * @param array<string, mixed> $values by phpName, over a monthly plan with no trial and no end
     */
    private function aPlan(Product $product, array $values = [], string $price = '9.900000', ?Currency $currency = null): SubscriptionPlan
    {
        $plan = (new SubscriptionPlan())
            ->setProductId($product->getId())
            ->setPeriodUnit(PeriodUnit::Month->value)
            ->setPeriodCount(1)
            ->setTrialDays(0)
            ->setPausable(0)
            ->setVisible(1)
            ->setPosition(1);

        foreach ($values as $name => $value) {
            $plan->setByName($name, $value);
        }

        $plan->setLocale('en_US')->setTitle('Monthly box');
        $plan->save($this->getPropelConnection());

        (new SubscriptionPlanPrice())
            ->setSubscriptionPlanId($plan->getId())
            ->setCurrencyId(($currency ?? $this->factory->currency())->getId())
            ->setPrice($price)
            ->setFromDefaultCurrency(0)
            ->save($this->getPropelConnection());

        return $plan;
    }

    private function aCustomer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * The order a customer took the subscription with, paid, through the carrier and the
     * recurring module of the shop.
     */
    private function anInitialOrder(Customer $customer): Order
    {
        return $this->factory->order($customer, [
            'statusCode' => OrderStatus::CODE_PAID,
            'paymentModuleCode' => RecurringTestPaymentModule::getModuleCode(),
            'deliveryModuleCode' => FixedPostageDeliveryModule::MODULE_CODE,
        ]);
    }

    /**
     * @param array<string, mixed> $planValues
     *
     * @return array{Subscription, Product, CustomerPaymentMethod}
     */
    private function aSubscription(array $planValues = [], int $stock = 10, bool $virtual = false, int $quantity = 1): array
    {
        $product = $this->aProduct($stock, $virtual);
        $plan = $this->aPlan($product, $planValues);
        $customer = $this->aCustomer();
        $method = $this->recurringMethodOf($customer, $this->recurringModule, $this->getPropelConnection());

        $subscription = $this->getService(SubscriptionFactory::class)->fromInitialOrder(
            $this->anInitialOrder($customer),
            $plan,
            $this->saleElementsOf($product),
            $quantity,
            $method,
        );

        return [$subscription, $product, $method];
    }

    private function biller(): SubscriptionInstallmentBiller
    {
        return $this->getService(SubscriptionInstallmentBiller::class);
    }

    private function reloaded(Subscription $subscription): Subscription
    {
        $fresh = SubscriptionQuery::create()->findPk($subscription->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }

    /**
     * @return list<Order> the orders of the installments placed by the biller, oldest first
     */
    private function installmentOrdersOf(Subscription $subscription): array
    {
        $initialOrderId = (int) $subscription->getInitialOrderId();
        $ids = [];

        foreach (SubscriptionOrderQuery::create()->filterBySubscriptionId($subscription->getId())->orderByInstallmentNumber()->find() as $installment) {
            if (null !== $installment->getOrderId() && (int) $installment->getOrderId() !== $initialOrderId) {
                $ids[] = (int) $installment->getOrderId();
            }
        }

        return array_values(array_map(
            static fn (int $id): Order => OrderQuery::create()->findPk($id) ?? throw new \RuntimeException('Order gone.'),
            $ids,
        ));
    }

    /**
     * The orders of the customer other than the initial one, whatever names them: an order
     * placed and lost by the biller would show here and not in installmentOrdersOf().
     */
    private function ordersPlacedFor(Subscription $subscription): int
    {
        return OrderQuery::create()
            ->filterByCustomerId($subscription->getCustomerId())
            ->filterById($subscription->getInitialOrderId(), Criteria::NOT_EQUAL)
            ->count();
    }
}
