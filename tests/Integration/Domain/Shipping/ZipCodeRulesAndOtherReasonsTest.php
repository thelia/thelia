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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Domain\Checkout\Exception\DeliveryExcludedByProductException;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\Product;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The postal code rules of the zones combined with the other reasons a carrier is not
 * offered: the buyer is told the real reason.
 */
final class ZipCodeRulesAndOtherReasonsTest extends IntegrationTestCase
{
    private FixtureFactory $factory;
    private Country $country;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->country = $this->factory->country(['isoalpha2' => 'XV', 'isoalpha3' => 'XVX', 'isocode' => '993']);
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * Every carrier serving the country refuses the postal code of the address: removing the
     * product would still leave no carrier, so the product is not what blocks the order.
     */
    public function testAProductIsNotBlamedWhenThePostalCodeLeavesNoCarrierAnyway(): void
    {
        $carriers = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->find();
        self::assertGreaterThan(0, $carriers->count());

        $area = $this->zone();
        foreach ($carriers as $carrier) {
            $this->serve($area, $carrier);
        }
        $this->exclude($area, '20*');

        [$cart, $product] = $this->cartShippedTo('20090');

        foreach ($carriers as $carrier) {
            $this->getService(ProductFacade::class)->excludeDeliveryModule($product->getId(), $carrier->getId());
        }

        try {
            $this->getService(CartGuard::class)->checkValidDelivery($cart);
            self::fail('A cart without carrier is refused.');
        } catch (InvalidDeliveryException $exception) {
            self::assertNotInstanceOf(
                DeliveryExcludedByProductException::class,
                $exception,
                'The postal code leaves no carrier whatever the products: the buyer is not told to remove a product.',
            );
        }
    }

    /**
     * Carrier A serves the address but does not quote the cart; carrier B would quote it and
     * only its postal code rule turns it away: the list is empty because of the postal code.
     */
    public function testThePostalCodeIsNamedWhenAnotherCarrierServesTheAddressButDoesNotQuoteTheCart(): void
    {
        $carriers = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->orderById()->limit(2)->find();
        self::assertCount(2, $carriers, 'Two active delivery modules are needed.');
        [$notQuoting, $refusedByThePostalCode] = [$carriers[0], $carriers[1]];

        $this->serve($this->zone(), $notQuoting);
        $restricted = $this->zone();
        $this->serve($restricted, $refusedByThePostalCode);
        $this->exclude($restricted, '20*');

        $notQuotingCode = $notQuoting->getCode();
        $listener = static function (DeliveryPostageEvent $event) use ($notQuotingCode): void {
            if ($event->getModule()->getCode() === $notQuotingCode) {
                $event->setValidModule(false);
            } else {
                $event->setValidModule(true)->setPostage(new OrderPostage(5.0, 0.0, 'Test'));
            }
            $event->stopPropagation();
        };
        $this->dispatcher()->addListener(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener, 2048);
        $this->registeredListeners[] = [TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener];

        [$cart] = $this->cartShippedTo('20090');
        $facade = $this->getService(ShippingFacade::class);

        self::assertSame([], $facade->listValidMethods($cart), 'No carrier is offered.');
        self::assertSame('20090', $facade->postalCodeWithoutCarrier($cart), 'The postal code is why no carrier is offered.');
    }

    private function zone(): Area
    {
        $area = (new Area())->setName('V6 postal code zone');
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($this->country->getId())->save($this->getPropelConnection());

        return $area;
    }

    private function serve(Area $area, Module $carrier): void
    {
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($carrier->getId())->save($this->getPropelConnection());
    }

    private function exclude(Area $area, string $pattern): void
    {
        (new AreaZipCodeRule())
            ->setAreaId($area->getId())
            ->setCountryId(null)
            ->setPattern($pattern)
            ->setExcluded(true)
            ->save($this->getPropelConnection());
    }

    /**
     * @return array{0: Cart, 1: Product}
     */
    private function cartShippedTo(string $zipCode): array
    {
        $address = $this->factory->cartAddress(null, $this->country, null, ['zipcode' => $zipCode]);
        $cart = $this->factory->cart();
        $cart->setAddressDeliveryId($address->getId())->save($this->getPropelConnection());
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->factory->cartItem($cart, $product);

        return [$cart, $product];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
