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

namespace Thelia\Tests\Http\BackOffice;

use Symfony\Component\Routing\RouterInterface;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdministratorSignIn;

/**
 * The component showcase of a shop that installs it, with nothing written in its configuration: the core
 * imports its routes, and outside debug (the test environment) every one of them answers a 404.
 */
final class ShowcaseRoutesTest extends WebIntegrationTestCase
{
    use AdministratorSignIn;

    private const string SHOWCASE_BUNDLE = 'OpenStudio\UxShowcaseBundle\OpenStudioUxShowcaseBundle';

    protected function setUp(): void
    {
        if (!class_exists(self::SHOWCASE_BUNDLE)) {
            self::markTestSkipped('The component showcase is not installed.');
        }

        parent::setUp();
    }

    public function testTheCoreImportsTheRoutesOfTheShowcaseUnderItsPrefix(): void
    {
        $routes = $this->getService(RouterInterface::class)->getRouteCollection();

        self::assertSame('/_showcase', $routes->get('ux_showcase_index')?->getPath());
        self::assertSame('/_showcase/{library}/{component}', $routes->get('ux_showcase_component')?->getPath());
    }

    public function testTheShowcaseDoesNotTakeTheHomePage(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseStatusCodeSame(200);
    }

    public function testTheShowcaseAnswersA404OutsideDebugByDefault(): void
    {
        $this->client->request('GET', '/_showcase');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheShowcaseStaysClosedToAnAdministratorWhenTheShopChoseNoAccess(): void
    {
        $this->signIn($this->administrator());

        $this->client->request('GET', '/_showcase');

        self::assertResponseStatusCodeSame(404);
    }
}
