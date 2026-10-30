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

namespace Thelia\Tests\Integration\Domain\Catalog\Bundle;

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDisplayedStock;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The stock a reader shows for a bundle: what its components allow (bike 7, two helmets out of 5,
 * the optional lock left out: 2), never the quantity of its own sale element.
 */
final class BundleDisplayedStockTest extends IntegrationTestCase
{
    use BuildsBundles;
    use RecordsSqlQueries;

    public function testABundleShowsWhatItsComponentsAllow(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));

        self::assertSame([(int) $example['bundle']->getId() => 2], $this->getService(BundleDisplayedStock::class)->forProducts([(int) $example['bundle']->getId()]));
    }

    public function testAProductThatIsNotABundleIsAbsent(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));

        $displayed = $this->getService(BundleDisplayedStock::class)->forProducts([(int) $example['bikeProduct']->getId(), (int) $example['bundle']->getId()]);

        self::assertSame([(int) $example['bundle']->getId()], array_keys($displayed));
        self::assertSame([], $this->getService(BundleDisplayedStock::class)->forProducts([(int) $example['bikeProduct']->getId()]));
        self::assertSame([], $this->getService(BundleDisplayedStock::class)->forProducts([]));
    }

    public function testABundleOfVirtualComponentsIsCappedToWhatAnIntegerHolds(): void
    {
        $factory = $this->createFixtureFactory();
        $service = $this->getService(BundleCompositionService::class);
        $virtual = $this->component($factory, '5', 0);
        $virtual->setVirtual(1)->save();
        $bundle = $this->bundleProduct($factory, $service);
        $service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($virtual)->getId(), '1');

        self::assertSame(
            [(int) $bundle->getId() => BundleDisplayedStock::DISPLAY_LIMIT],
            $this->getService(BundleDisplayedStock::class)->forProducts([(int) $bundle->getId()]),
        );
    }

    public function testTheNumberOfQueriesDoesNotGrowWithTheNumberOfBundles(): void
    {
        $factory = $this->createFixtureFactory();
        $service = $this->getService(BundleCompositionService::class);
        $stock = $this->getService(BundleDisplayedStock::class);
        $first = $this->exampleBundle($factory, $service)['bundle'];
        $ids = [(int) $first->getId()];
        $one = \count($this->recordSqlQueries(static fn () => $stock->forProducts($ids)));

        for ($n = 0; $n < 3; ++$n) {
            $ids[] = (int) $this->exampleBundle($factory, $service)['bundle']->getId();
        }

        self::assertSame($one, \count($this->recordSqlQueries(static fn () => $stock->forProducts($ids))));
    }
}
