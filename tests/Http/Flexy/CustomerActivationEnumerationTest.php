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
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The account activation pages must not answer differently for an address that has
 * an account and for one that does not: the difference alone is enough to tell
 * whether someone is a customer of the shop, without logging in.
 *
 * These tests drive the routes of the installed Flexy theme. A theme older than the
 * fix names the address in the url and is reported as skipped rather than failed,
 * because the theme ships as its own package on its own release cycle.
 */
final class CustomerActivationEnumerationTest extends WebIntegrationTestCase
{
    public function testActivationAnswersTheSameWhetherTheAddressHasAnAccountOrNot(): void
    {
        $this->skipUnlessTheThemeKeepsTheAddressOutOfTheUrl('customer_activation');

        self::assertSame(
            $this->answerFor('customer_activation', $this->addressWithAnAccount()),
            $this->answerFor('customer_activation', $this->addressWithoutAnAccount()),
        );
    }

    public function testSendCodeAnswersTheSameWhetherTheAddressHasAnAccountOrNot(): void
    {
        $this->skipUnlessTheThemeKeepsTheAddressOutOfTheUrl('customer_send_code');

        self::assertSame(
            $this->answerFor('customer_send_code', $this->addressWithAnAccount()),
            $this->answerFor('customer_send_code', $this->addressWithoutAnAccount()),
        );
    }

    /**
     * Everything a caller can read from the response: an activation flow that leaks
     * through any of these is as good as one that says "this address is a customer".
     *
     * @return array<string, string|int|null>
     */
    private function answerFor(string $route, string $email): array
    {
        $url = $this->getService(RouterInterface::class)->generate($route, ['email' => $email]);

        $this->client->request('GET', $url);
        $response = $this->client->getResponse();

        return [
            'status' => $response->getStatusCode(),
            'location' => $response->headers->get('Location'),
            'length' => \strlen((string) $response->getContent()),
        ];
    }

    private function addressWithAnAccount(): string
    {
        // Built without createFixtureFactory(): that helper pushes a synthetic request
        // when the stack is empty, and it would then be the "main" request of the calls
        // below, hiding a leak read from it.
        $factory = new FixtureFactory($this->getPropelConnection());

        return (string) $factory->customer($factory->customerTitle())->getEmail();
    }

    private function addressWithoutAnAccount(): string
    {
        return 'no-account-'.bin2hex(random_bytes(8)).'@example.com';
    }

    private function skipUnlessTheThemeKeepsTheAddressOutOfTheUrl(string $route): void
    {
        $definition = $this->getService(RouterInterface::class)->getRouteCollection()->get($route);

        if (null === $definition) {
            self::markTestSkipped(\sprintf('The installed front-office theme has no "%s" route.', $route));
        }

        if (str_contains($definition->getPath(), '{email}')) {
            self::markTestSkipped(\sprintf('The installed Flexy theme still names the address in the "%s" url; the release that stopped doing so is not installed yet.', $route));
        }
    }
}
