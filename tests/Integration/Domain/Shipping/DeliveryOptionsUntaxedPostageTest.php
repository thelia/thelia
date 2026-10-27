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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Shipping\Service\DeliveryOptionsProvider;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * A carrier may quote the postage and its tax alone: the option the buyer reads is always
 * published with its untaxed amount too, so that a theme showing prices without tax
 * subtracts nothing.
 */
final class DeliveryOptionsUntaxedPostageTest extends IntegrationTestCase
{
    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testAnOptionQuotedWithoutItsUntaxedAmountIsCompleted(): void
    {
        $options = $this->optionsQuotedBy(
            (new DeliveryModuleOption())->setCode('tax-only')->setValid(true)->setPostage(12.0)->setPostageTax(2.0),
        );

        self::assertCount(1, $options);
        self::assertEqualsWithDelta(10.0, $options[0]->getPostageUntaxed(), 1e-9);
    }

    public function testAnOptionWithoutTaxIsUntaxedAtItsAmount(): void
    {
        $options = $this->optionsQuotedBy(
            (new DeliveryModuleOption())->setCode('amount-only')->setValid(true)->setPostage(7.5),
        );

        self::assertEqualsWithDelta(7.5, $options[0]->getPostageUntaxed(), 1e-9);
    }

    public function testTheUntaxedAmountACarrierGaveIsKept(): void
    {
        $options = $this->optionsQuotedBy(
            (new DeliveryModuleOption())->setCode('complete')->setValid(true)->setPostage(12.0)->setPostageTax(2.0)->setPostageUntaxed(9.99),
        );

        self::assertEqualsWithDelta(9.99, $options[0]->getPostageUntaxed(), 1e-9);
    }

    public function testAnOptionWithoutAPriceStaysWithoutOne(): void
    {
        $options = $this->optionsQuotedBy(
            (new DeliveryModuleOption())->setCode('unavailable')->setValid(false),
        );

        self::assertNull($options[0]->getPostageUntaxed());
    }

    /**
     * @return list<DeliveryModuleOption>
     */
    private function optionsQuotedBy(DeliveryModuleOption ...$quoted): array
    {
        $listener = static function (DeliveryModuleOptionEvent $event) use ($quoted): void {
            foreach ($quoted as $option) {
                $event->appendDeliveryModuleOptions($option);
            }

            $event->stopPropagation();
        };

        $this->dispatcher()->addListener(TheliaEvents::MODULE_DELIVERY_GET_OPTIONS, $listener, 1024);
        $this->registeredListeners[] = [TheliaEvents::MODULE_DELIVERY_GET_OPTIONS, $listener];

        $module = ModuleQuery::create()->findOneByCode('CustomDelivery');
        self::assertNotNull($module);

        $cart = $this->createFixtureFactory()->cart();
        self::assertInstanceOf(Cart::class, $cart);

        return $this->getService(DeliveryOptionsProvider::class)->getOptions($module, null, $cart, Country::getDefaultCountry(), null);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
