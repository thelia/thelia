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

namespace Thelia\Tests\Api\Front;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Test\ApiTestCase;

/**
 * A customer group that moves the price of a line between the filling of the cart and the
 * placement of the order, over the stateless API: the promotion that took 10 % off the old
 * total must take 10 % off the new one before the same call goes through. No session stands
 * behind the request, so nothing but the placement itself can price the discount again.
 */
final class CheckoutPricesChangedApiTest extends ApiTestCase
{
    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    public function testAGroupChangedBeforeThePlacementIsAnswered409ThenPlacedWithTheCouponOnTheNewTotal(): void
    {
        $factory = $this->createFixtureFactory();
        $this->turnMandatoryConsentsOff();
        $country = $factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery') ?? throw new \RuntimeException('No delivery module installed.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque') ?? throw new \RuntimeException('No Cheque module installed.');
        $this->serveCountryWith($deliveryModule->getId(), $country->getId());
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(7.5, 1.5, 'VAT'));
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });

        $factory->coupon([
            'code' => null,
            'triggerMode' => 'automatic',
            'type' => 'thelia.coupon.type.remove_x_percent',
            'effects' => ['percentage' => 10.0],
            'conditions' => $this->atLeastOneArticle(),
            'isCumulative' => true,
        ]);
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $country);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100, 'basePrice' => 100.0]);
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $product, overrides: ['price' => '100.000000', 'promoPrice' => '100.000000']);

        $token = $this->authenticateAsCustomer($customer);
        $cartId = $cart->getId();
        $this->jsonRequest('POST', $this->url($cartId, 'delivery_address'), ['addressId' => $address->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cartId, 'invoice_address'), ['addressId' => $address->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cartId, 'delivery_module'), ['deliveryModuleId' => $deliveryModule->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cartId, 'payment_module'), ['paymentModuleId' => $paymentModule->getId()], token: $token);

        // The cart as the buyer filled it, the promotion priced on 100.
        $oldDiscount = round(0.10 * $this->taxedTotalOf($cartId, $country), 2);
        CartQuery::create()->filterById($cartId)->update(['Discount' => $oldDiscount]);
        CartTableMap::clearInstancePool();
        self::assertGreaterThan(0.0, $oldDiscount);

        $factory->assignCustomerGroup($customer, $factory->customerGroup('pro-at-placement', 15.0));

        $refused = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);
        self::assertSame(409, $refused->getStatusCode(), (string) $refused->getContent());
        self::assertSame([], OrderQuery::create()->filterByCartId($cartId)->find()->getData(), 'nothing is ordered on the old total');

        $newTotal = $this->taxedTotalOf($cartId, $country);
        CartItemTableMap::clearInstancePool();
        self::assertSame(85.0, round((float) CartItemQuery::create()->filterByCartId($cartId)->findOne()?->getPrice(), 6), 'the line was priced again for the group');
        self::assertEqualsWithDelta(round(0.10 * $newTotal, 2), (float) CartQuery::create()->findPk($cartId)?->getDiscount(), 0.001, 'the refusal priced the coupon on the new lines');
        self::assertNotEqualsWithDelta($oldDiscount, (float) CartQuery::create()->findPk($cartId)?->getDiscount(), 0.001);

        $placed = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);
        self::assertSame(200, $placed->getStatusCode(), (string) $placed->getContent());
        $order = OrderQuery::create()->findPk(json_decode((string) $placed->getContent(), true, flags: \JSON_THROW_ON_ERROR)['orderId']);
        self::assertNotNull($order);
        self::assertEqualsWithDelta(round(0.10 * $newTotal, 2), (float) $order->getDiscount(), 0.001, 'the order carries 10 % of the total the buyer was shown');
    }

    private function taxedTotalOf(int $cartId, \Thelia\Model\Country $country): float
    {
        CartItemTableMap::clearInstancePool();
        $total = 0.0;

        foreach (CartItemQuery::create()->filterByCartId($cartId)->find() as $line) {
            $total += $line->getTotalRealTaxedPrice($country);
        }

        return $total;
    }

    private function atLeastOneArticle(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => 1],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }

    private function url(int $cartId, string $step): string
    {
        return '/api/front/account/checkout/'.$cartId.'/'.$step;
    }

    private function serveCountryWith(int $moduleId, int $countryId): void
    {
        $area = (new Area())->setName('Prices changed API test area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($countryId)->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($moduleId)->save($this->getPropelConnection());
    }

    private function turnMandatoryConsentsOff(): void
    {
        ConsentTableMap::clearInstancePool();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
