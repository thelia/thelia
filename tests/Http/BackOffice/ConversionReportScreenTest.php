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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The "Reports > Conversion" screen of the back-office theme, which reads the
 * conversion funnel of the core. Guarded by the order resource.
 */
final class ConversionReportScreenTest extends WebIntegrationTestCase
{
    private const URL = '/admin/reports/conversion';

    private ?AdminSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The screen lives in the back-office theme, a separate composer package
        // installed from its upstream branch: skip until the theme ships it.
        if (!class_exists('BackOfficeDefaultTwigBundle\\Service\\Report\\ConversionReportProvider')) {
            self::markTestSkipped('The installed back-office theme has no conversion report screen.');
        }

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        // Nullable and guarded: tearDown() still runs after setUp() skipped the test.
        $this->injector?->clear();
        parent::tearDown();
    }

    public function testTheFunnelOfThePeriodIsServedToAnAdminHoldingTheOrderResource(): void
    {
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::URL.'?period=7days');

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('data-testid="report-funnel-table"', $html);
        self::assertSame(6, preg_match_all('/<tr[^>]*data-testid="report-step-[a-z_]+"/', $html));
        self::assertStringContainsString('data-testid="report-conversion-rate"', $html);
    }

    public function testAnAdminWithoutTheOrderResourceIsRefused(): void
    {
        $this->loginAs($this->factory()->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW],
        ]));

        $this->client->request('GET', self::URL);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    private function factory(): FixtureFactory
    {
        // Deliberately not createFixtureFactory(): that helper pushes a synthetic
        // request, which would then be the "main" request the security context
        // reads its session from.
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector?->setAdmin($admin);
    }
}
