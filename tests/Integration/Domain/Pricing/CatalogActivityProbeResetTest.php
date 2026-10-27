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

namespace Thelia\Tests\Integration\Domain\Pricing;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The activity probe is a service of the kernel like any other: the kernel forgets it with
 * the request, and the services that read it do not forget it for it. A service reset on
 * its own keeps what the probe read, and only the kernel reset reads the shop again.
 */
final class CatalogActivityProbeResetTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function servicesReadingTheProbe(): iterable
    {
        yield 'the group resolver' => [CustomerGroupResolver::class];
        yield 'the category visibility' => [CategoryVisibility::class];
        yield 'the sale audience checker' => [SaleAudienceChecker::class];
        yield 'the customer price context' => [CustomerPriceContext::class];
    }

    /**
     * @param class-string $service
     */
    #[DataProvider('servicesReadingTheProbe')]
    public function testAServiceThatReadsTheProbeDoesNotResetIt(string $service): void
    {
        $probe = $this->getService(CatalogActivityProbe::class);
        $probe->reset();
        $probe->read();

        $this->getService($service)->reset();

        $statements = $this->recordSqlQueries(static fn () => $probe->read());

        self::assertSame([], $statements, 'What the probe read is kept until the kernel resets it.');
    }

    public function testTheKernelResetMakesTheProbeReadTheShopAgain(): void
    {
        $probe = $this->getService(CatalogActivityProbe::class);
        $probe->read();

        $this->getService('services_resetter')->reset();

        $statements = $this->recordSqlQueries(static fn () => $probe->read());

        self::assertCount(1, $statements, 'One statement, the one of the request.');
    }
}
