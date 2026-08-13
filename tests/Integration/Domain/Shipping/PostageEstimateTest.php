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

use Thelia\Domain\Shipping\DTO\PostageEstimateView;
use Thelia\Domain\Shipping\ShippingFacade;
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
}
