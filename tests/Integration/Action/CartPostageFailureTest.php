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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Cart as CartAction;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Module\BaseModule;
use Thelia\Module\Exception\DeliveryException;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * CART_SET_DELIVERY_MODULE writes the module on the cart before CART_SET_POSTAGE
 * asks it for a postage. A module that cannot ship the cart must not stay there:
 * the checkout would go on to the payment with a carrier that never quoted.
 */
final class CartPostageFailureTest extends ActionIntegrationTestCase
{
    public function testAModuleThatCannotShipTheCartIsTakenOffIt(): void
    {
        $cart = $this->cartWithADeliveryModule();

        $this->quote($cart, null);

        self::assertNull($cart->getDeliveryModuleId());
        self::assertNull($cart->getPostage());
        self::assertSame(0.0, (float) $cart->getPostageTax());
    }

    public function testAModuleThatQuotesStaysOnTheCart(): void
    {
        $cart = $this->cartWithADeliveryModule();
        $moduleId = $cart->getDeliveryModuleId();

        $this->quote($cart, new OrderPostage(12.0, 2.0, 'VAT 20'));

        self::assertSame($moduleId, $cart->getDeliveryModuleId());
        self::assertEqualsWithDelta(12.0, (float) $cart->getPostage(), 0.0001);
    }

    /**
     * Runs the real CART_SET_POSTAGE listener; only the quote of the module is
     * replaced, by a refusal when $postage is null.
     */
    private function quote(Cart $cart, ?OrderPostage $postage): void
    {
        $action = new class extends CartAction {
            public ?OrderPostage $postage = null;

            public function __construct()
            {
                // The overridden method below is the only dependency of calculatePostage().
            }

            protected function getPostageByDeliveryModuleId(
                Cart $cart,
                EventDispatcherInterface $dispatcher,
                int $moduleId,
                int $deliveryAddressId,
            ): OrderPostage {
                return $this->postage ?? throw new DeliveryException('Delivery module is not available for this cart');
            }
        };
        $action->postage = $postage;

        $action->calculatePostage(new CartCheckoutEvent($cart), TheliaEvents::CART_SET_POSTAGE, $this->dispatcher);

        $cart->reload();
    }

    private function cartWithADeliveryModule(): Cart
    {
        $currency = $this->factory->currency();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $country = $this->factory->country();

        $address = (new CartAddress())
            ->setCustomerTitleId($customerTitle->getId())
            ->setFirstname('Jane')
            ->setLastname('Doe')
            ->setAddress1('1 rue du Port')
            ->setZipcode('44000')
            ->setCity('Nantes')
            ->setCountryId($country->getId());
        $address->save($this->getPropelConnection());

        $deliveryModule = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->findOne()
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken(uniqid('postage-failure-', true))
            ->setAddressDeliveryId($address->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPostage('5.00')
            ->setPostageTax('1.00');
        $cart->save($this->getPropelConnection());

        return $cart;
    }
}
