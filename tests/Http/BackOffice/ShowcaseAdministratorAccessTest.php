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

use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdministratorSignIn;
use Thelia\Tests\Support\Kernel\ShowcaseAdministratorKernel;

/**
 * The showcase of a shop that names the access of the core in `ux_showcase.access.service`: outside debug, it
 * answers the administrators of the back office, and a 404 to everyone else.
 */
final class ShowcaseAdministratorAccessTest extends WebIntegrationTestCase
{
    use AdministratorSignIn;

    protected static function getKernelClass(): string
    {
        return ShowcaseAdministratorKernel::class;
    }

    protected function setUp(): void
    {
        if (!class_exists('OpenStudio\UxShowcaseBundle\OpenStudioUxShowcaseBundle')) {
            self::markTestSkipped('The component showcase is not installed.');
        }

        parent::setUp();
    }

    public function testAVisitorGetsA404(): void
    {
        $this->client->request('GET', '/_showcase');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnAdministratorSignedInGetsTheShowcase(): void
    {
        $this->signIn($this->administrator());

        $this->client->request('GET', '/_showcase');

        self::assertResponseIsSuccessful();
        self::assertSame('noindex, nofollow', $this->client->getResponse()->headers->get('X-Robots-Tag'));
    }
}
