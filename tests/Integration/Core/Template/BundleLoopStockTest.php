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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Model\Currency;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The loops show the stock of a bundle as what its components allow (2 for the bundle of the plan),
 * while its own sale element keeps a quantity of zero.
 */
final class BundleLoopStockTest extends IntegrationTestCase
{
    use BuildsBundles;

    public function testTheProductLoopShowsWhatTheComponentsAllow(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));

        $row = $this->firstRow('product', ['id' => $example['bundle']->getId()]);

        self::assertEquals(2, $row['QUANTITY']);
    }

    public function testTheSaleElementsLoopShowsWhatTheComponentsAllow(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));

        $row = $this->firstRow('product_sale_elements', ['product' => $example['bundle']->getId()]);

        self::assertEquals(2, $row['QUANTITY']);
    }

    public function testTheLoopsKeepTheQuantityOfAProductThatIsNotABundle(): void
    {
        $factory = $this->createFixtureFactory();
        $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $simple = $this->component($factory, '12.5', 3, ['title' => 'Simple']);

        self::assertEquals(3, $this->firstRow('product', ['id' => $simple->getId()])['QUANTITY']);
        self::assertEquals(3, $this->firstRow('product_sale_elements', ['product' => $simple->getId()])['QUANTITY']);
    }

    public function testTheBackOfficeKeepsTheQuantityOfTheSaleElementOfTheBundle(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));

        $row = $this->firstRow('product_sale_elements', ['product' => $example['bundle']->getId(), 'backend_context' => true]);

        self::assertEquals(0, $row['QUANTITY']);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function firstRow(string $loop, array $arguments): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $result = $this->getService(LoopExecutor::class)->execute($loop, $arguments + [
            'lang' => $lang->getId(),
            'currency' => Currency::getDefaultCurrency()->getId(),
            'visible' => '*',
        ]);

        foreach ($result as $row) {
            return $row->getVarVal();
        }

        self::fail('The loop answered no row.');
    }
}
