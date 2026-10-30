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

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The sheet of a bundle reads the visuals of its components in one go and prices the composition once: the number
 * of SQL statements of the page does not grow with the number of components, whatever their number.
 *
 * The page belongs to the theme, which ships as its own package on its own release cycle: a theme older than the
 * bundles is reported as skipped rather than failed.
 */
final class ProductBundleQueryBudgetTest extends WebIntegrationTestCase
{
    use CreatesTestFiles;
    use RecordsSqlQueries;

    /**
     * What the six-component sheet may cost more than the three-component one. Measured at zero: the visuals, the
     * titles, the sale elements, the stocks and the quote are all read once for the whole list.
     */
    private const ALLOWED_EXTRA_STATEMENTS = 0;

    protected function setUp(): void
    {
        if (!class_exists(\FlexyBundle\Components\Organisms\BundleComposition\Base::class)) {
            self::markTestSkipped('The installed front-office theme does not know the product bundles.');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();

        parent::tearDown();
    }

    public function testTheStatementsOfTheSheetDoNotGrowWithTheNumberOfComponents(): void
    {
        // One category for both: the menu and the footer of the page read it, which has nothing to do with the components.
        $factory = new FixtureFactory($this->getPropelConnection());
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $this->bundleOf($factory, $category, $taxRule, $currency, 3, 'flexy-bundle-budget-three.html');
        $this->bundleOf($factory, $category, $taxRule, $currency, 6, 'flexy-bundle-budget-six.html');

        // The first render of the process warms what every page shares (configuration, translations, theme).
        $this->render('/flexy-bundle-budget-three.html');

        $three = $this->statementsOf('/flexy-bundle-budget-three.html');
        $six = $this->statementsOf('/flexy-bundle-budget-six.html');

        self::assertSame(
            6,
            substr_count((string) $this->client->getResponse()->getContent(), 'data-testid="bundle-component"'),
            'the six components are listed',
        );
        self::assertLessThanOrEqual(
            \count($three) + self::ALLOWED_EXTRA_STATEMENTS,
            \count($six),
            \sprintf("3 components: %d statements, 6 components: %d.\n%s", \count($three), \count($six), self::describeGrowth($three, $six)),
        );
    }

    /**
     * @param list<string> $three
     * @param list<string> $six
     */
    private static function describeGrowth(array $three, array $six): string
    {
        $tables = static function (array $statements): array {
            $counts = [];

            foreach ($statements as $statement) {
                $table = 1 === preg_match('/(?:FROM|INTO|UPDATE) `(\w+)`/', $statement, $match) ? $match[1] : 'other';
                $counts[$table] = ($counts[$table] ?? 0) + 1;
            }

            return $counts;
        };
        $before = $tables($three);
        $after = $tables($six);
        $growth = [];

        foreach ($after as $table => $count) {
            if ($count !== ($before[$table] ?? 0)) {
                $growth[] = \sprintf('%s: %d -> %d', $table, $before[$table] ?? 0, $count);
            }
        }

        return 'tables read more often: '.implode(', ', $growth)."\n".implode("\n", array_map(static fn (string $s): string => substr($s, -260), array_filter($six, static fn (string $s): bool => str_contains($s, 'category_i18n'))));
    }

    /**
     * @return list<string>
     */
    private function statementsOf(string $url): array
    {
        return $this->recordSqlQueries(fn () => $this->render($url));
    }

    private function render(string $url): void
    {
        $this->assertPageRenders($url);
    }

    /**
     * The factory is built without createFixtureFactory(): that helper pushes a synthetic request that would become the main request of the page.
     */
    private function bundleOf(FixtureFactory $factory, Category $category, TaxRule $taxRule, Currency $currency, int $components, string $url): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $bundle = $factory->product($category, $taxRule, $currency, ['title' => 'Budget bundle '.$components, 'basePrice' => 999.0, 'baseQuantity' => 0]);
        $bundle = $service->setBundleSettings((int) $bundle->getId(), true, BundlePriceMode::Computed, '10', BundleDiscountType::Percent);
        $bundle->setRewrittenUrl('en_US', $url);

        for ($index = 1; $index <= $components; ++$index) {
            $component = $factory->product($category, $taxRule, $currency, ['title' => 'Budget component '.$components.'-'.$index, 'basePrice' => 10.0 * $index, 'baseQuantity' => 10]);
            $image = $factory->productImage($component, ['position' => 1, 'title' => 'Visual '.$components.'-'.$index]);
            $this->trackFileForCleanup($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile());
            $service->addItem((int) $bundle->getId(), (int) $component->getProductSaleElementss()->getFirst()->getId(), '1', null, null, 1 === $index % 2);
        }
    }
}
