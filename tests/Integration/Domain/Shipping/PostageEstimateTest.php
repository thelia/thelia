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

use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Shipping\DTO\EstimatedPostageDTO;
use Thelia\Domain\Shipping\DTO\PostageEstimateView;
use Thelia\Domain\Shipping\Service\DeliverySetupService;
use Thelia\Domain\Shipping\Service\PostageEstimator;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The postage estimator answers with an EstimatedPostageDTO. Reading that
 * answer as an array is a fatal error, whatever the cart contains and whatever
 * delivery modules are installed.
 */
final class PostageEstimateTest extends IntegrationTestCase
{
    public function testEstimatingCartPostageAnswersAView(): void
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart();
        $country = $factory->country();

        $view = $this->getService(ShippingFacade::class)->estimateCartPostage($cart, $country);

        self::assertInstanceOf(PostageEstimateView::class, $view);

        if (null !== $view->amountHt && null !== $view->tax) {
            self::assertEqualsWithDelta($view->amountHt + $view->tax, $view->totalTtc, 0.0001);
        }
    }

    public function testVirtualDeliverySetupStoresTheEstimatedPostage(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $factory->address($customer);
        $cart = $factory->cart($customer);

        $this->activateVirtualDeliveryModule();

        $estimator = $this->createMock(PostageEstimator::class);
        $estimator->method('estimatePostageForCountry')
            ->willReturn(new EstimatedPostageDTO(7.5, 1.5));

        $setup = new DeliverySetupService($estimator, $this->createMock(CartAddressService::class));
        $setup->setupVirtualDelivery($cart);

        self::assertSame(7.5, (float) $cart->getPostage());
        self::assertSame(1.5, (float) $cart->getPostageTax());
    }

    private function activateVirtualDeliveryModule(): void
    {
        $module = ModuleQuery::create()->findOneByCode('VirtualProductDelivery')
            ?? throw new \RuntimeException('The VirtualProductDelivery module is not installed — run bin/test-prepare.');

        $module->setActivate(1)->save($this->getPropelConnection());
    }
}
