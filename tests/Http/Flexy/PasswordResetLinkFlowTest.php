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

use Symfony\Component\Routing\RouterInterface;
use Thelia\Domain\Customer\Service\PasswordResetService;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The reset link has to work from end to end: the page it leads to takes a new password,
 * the account then answers to it, and the same link stops working right after.
 *
 * These tests drive the routes of the installed Flexy theme. A theme older than the one
 * that added the reset page is reported as skipped rather than failed, because the theme
 * ships as its own package on its own release cycle.
 */
final class PasswordResetLinkFlowTest extends WebIntegrationTestCase
{
    public function testTheLinkLeadsToAPageThatAsksForTheNewPassword(): void
    {
        $this->skipUnlessTheThemeHasTheResetPage();

        $token = $this->tokenFor($this->customer());

        $crawler = $this->client->request('GET', $this->resetUrl($token));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(
            1,
            $crawler->filter('form[name="thelia_customer_reset_password"] input[type="password"][name="thelia_customer_reset_password[password]"]'),
            'The page behind a usable link must ask for the new password.',
        );
    }

    public function testTheSameLinkCannotBeFollowedTwice(): void
    {
        $this->skipUnlessTheThemeHasTheResetPage();

        $customer = $this->customer();
        $token = $this->tokenFor($customer);

        $this->getService(PasswordResetService::class)->resetPassword($token, 'a-brand-new-password');

        $this->client->request('GET', $this->resetUrl($token));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString(
            'thelia_customer_reset_password[password]',
            (string) $response->getContent(),
            'A link that has already been used must not offer the form again.',
        );
    }

    private function resetUrl(string $token): string
    {
        return $this->getService(RouterInterface::class)->generate('password_reset', ['token' => $token]);
    }

    private function tokenFor(Customer $customer): string
    {
        return $this->getService(PasswordResetService::class)->createToken($customer);
    }

    private function customer(): Customer
    {
        $factory = new FixtureFactory($this->getPropelConnection());

        return $factory->customer($factory->customerTitle());
    }

    private function skipUnlessTheThemeHasTheResetPage(): void
    {
        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get('password_reset')) {
            self::markTestSkipped('The installed front-office theme has no "password_reset" route yet.');
        }
    }
}
