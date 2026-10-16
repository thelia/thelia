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

namespace Thelia\Tests\Api\Front;

use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A shop that never created a sale unit keeps the queries it had before the
 * feature: once the shared entry says no unit is active, a front request asks the
 * database nothing about sale units, whatever it reads.
 */
final class SaleUnitSingleShopQueryCountTest extends ApiTestCase
{
    use RecordsSqlQueries;

    public function testAFrontRequestOfASingleShopReadsNoSaleUnitTable(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 10]);
        $uri = '/api/front/products/'.$product->getId();

        // The first request of the shop writes the shared entry.
        self::assertJsonResponseSuccessful($this->jsonRequest('GET', $uri));

        $statements = $this->recordSqlQueries(function () use ($uri): void {
            // A later request is another process: nothing of the first one is
            // remembered in memory, only what the shared pool kept.
            $this->getService(SaleUnitActivityChecker::class)->reset();

            self::assertJsonResponseSuccessful($this->jsonRequest('GET', $uri));
        });

        self::assertNotSame([], $statements, 'The request is recorded: it reads the product it serves.');
        self::assertSame([], array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/FROM `sale_unit(_\w+)?`/', $statement),
        )), 'A single shop must not ask the database about sale units on every request.');
    }
}
