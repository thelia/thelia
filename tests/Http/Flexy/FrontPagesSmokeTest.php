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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Test\WebIntegrationTestCase;

/**
 * Smoke tests for the Flexy front-office routing and controller wiring.
 *
 * A page that renders a template must answer 200. The only tolerated outcome is
 * a theme whose assets are not built, which {@see WebIntegrationTestCase::assertPageRenders()}
 * reports as a skipped test: the core CI installs the theme from Composer and
 * never runs `npm run build`, while the theme's own CI does and therefore gets
 * the full assertion.
 */
final class FrontPagesSmokeTest extends WebIntegrationTestCase
{
    public function testHomepageRenders(): void
    {
        $this->assertPageRenders('/');
    }

    public function testAccountRedirectsWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/account');

        self::assertResponseRedirects();
    }

    public function testAccountOrdersRedirectsWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/account/orders');

        self::assertResponseRedirects();
    }

    public function testAccountAddressesRedirectsWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/account/addresses');

        self::assertResponseRedirects();
    }

    public function testCustomerLoginRenders(): void
    {
        $this->assertPageRenders('/customer/login');
    }

    public function testCustomerRegisterRenders(): void
    {
        $this->assertPageRenders('/customer/register');
    }

    public function testCheckoutCartRenders(): void
    {
        $this->assertPageRenders('/checkout/cart');
    }

    public function testPasswordForgottenRouteRedirects(): void
    {
        $this->client->request('GET', '/password/forgotten/confirm');

        // This route redirects to /password/forgotten.
        self::assertResponseRedirects();
    }

    public function testCheckoutDeliveryRedirectsWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/checkout/delivery');

        self::assertResponseRedirects();
    }

    public function testCheckoutPaymentRedirectsWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/checkout/payment');

        self::assertResponseRedirects();
    }
}
