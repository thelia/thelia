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

namespace Thelia\Tests\Integration\Domain\Checkout;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Domain\Checkout\Service\ExpressCheckoutGate;
use Thelia\Test\IntegrationTestCase;

/**
 * A request with no session holds no cart: it is refused the way the script reads, not
 * with the exception Symfony raises when a session is asked of a request that has none.
 */
final class ExpressCheckoutGateTest extends IntegrationTestCase
{
    public function testARequestWithoutASessionIsRefusedForWantOfACart(): void
    {
        $this->expectException(ExpressCheckoutRefusedException::class);

        $this->getService(ExpressCheckoutGate::class)->open(Request::create('/checkout/express/Cheque/confirm', 'POST'), 'Cheque', 'token');
    }
}
