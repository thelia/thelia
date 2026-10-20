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

namespace Thelia\Tests\Integration\Core\Template;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Domain\Shipping\ProductRule\ShippingSurchargeMode;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The `delivery` loop lists the carriers of a template: it offers the ones the
 * products of the cart accept, at the price the cart is charged.
 */
final class DeliveryLoopShippingRulesTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testACarrierAProductRefusesIsLeftOutOfTheLoop(): void
    {
        $carrier = $this->carrier();
        $country = $this->factory->country();
        $this->serveCountryWith($carrier, $country);
        $product = $this->cartInTheSessionWith();
        $this->answerDeliveryQuote();

        self::assertArrayHasKey($carrier->getCode(), $this->deliveryRows($country), 'The carrier is listed while no product refuses it.');

        $this->getService(ProductFacade::class)->excludeDeliveryModule($product->getId(), $carrier->getId());

        self::assertArrayNotHasKey($carrier->getCode(), $this->deliveryRows($country));
    }

    public function testThePostageListedCarriesTheSurchargeOfTheProducts(): void
    {
        $carrier = $this->carrier();
        $country = $this->factory->country();
        $this->serveCountryWith($carrier, $country);
        $this->cartInTheSessionWith('10', ShippingSurchargeMode::PerLine);
        $this->answerDeliveryQuote();

        $row = $this->deliveryRows($country)[$carrier->getCode()] ?? null;

        self::assertNotNull($row, 'The carrier is listed.');
        // The carrier quotes 10.00 untaxed, the product adds 10.00.
        self::assertEqualsWithDelta(20.0, (float) $row['POSTAGE_UNTAXED'], 0.0001);
        self::assertEqualsWithDelta(20.0, (float) $row['POSTAGE'] - (float) $row['POSTAGE_TAX'], 0.0001);
    }

    private function carrier(): Module
    {
        return ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed - run bin/test-prepare.');
    }

    private function cartInTheSessionWith(string $surcharge = '0', ShippingSurchargeMode $mode = ShippingSurchargeMode::PerLine): Product
    {
        $cart = $this->factory->cart();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
        $product->setShippingSurcharge($surcharge)->setShippingSurchargeMode($mode->value)->save();
        $this->factory->cartItem($cart, $product);

        $session = $this->getService(RequestStack::class)->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setSessionCart($cart);

        return $product;
    }

    private function serveCountryWith(Module $carrier, Country $country): void
    {
        $area = (new Area())->setName('Delivery loop test area');
        $area->save();
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save();
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($carrier->getId())->save();
    }

    /**
     * Every carrier quotes 12.00 with 2.00 of tax.
     */
    private function answerDeliveryQuote(): void
    {
        $listener = static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(12.0, 2.0, 'VAT 20'));
            $event->stopPropagation();
        };
        $this->dispatcher()->addListener(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener, 1024);
        $this->registeredListeners[] = [TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener];
    }

    /**
     * @return array<string, array<string, mixed>> module code => row
     */
    private function deliveryRows(Country $country): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();

        $result = $this->getService(LoopExecutor::class)->execute('delivery', [
            'country' => $country->getId(),
            'lang' => $lang?->getId(),
        ]);

        $rows = [];
        foreach ($result as $row) {
            $values = $row->getVarVal();
            $rows[(string) $values['CODE']] = $values;
        }

        return $rows;
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
