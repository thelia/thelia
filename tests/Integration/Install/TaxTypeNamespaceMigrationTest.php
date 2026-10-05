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

namespace Thelia\Tests\Integration\Install;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\Propel;
use Thelia\Domain\Taxation\TaxEngine\Exception\TaxEngineException;
use Thelia\Domain\Taxation\TaxEngine\TaxType\FeatureFixAmountTaxType;
use Thelia\Domain\Taxation\TaxEngine\TaxType\FixAmountTaxType;
use Thelia\Domain\Taxation\TaxEngine\TaxType\PricePercentTaxType;
use Thelia\Model\Country;
use Thelia\Model\FeatureProduct;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\TaxTableMap;
use Thelia\Model\Product;
use Thelia\Model\Tax;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;

/**
 * A tax records the class of its type, and the three tax types changed namespace
 * between Thelia 2 and Thelia 3. A tax still carrying its Thelia 2 class name
 * matches no tax type, and every product under its rule fails to be priced.
 * 3.0.0-alpha1.sql renames the classes of a shop being migrated, 3.3.0.sql those
 * of a shop that was migrated before it renamed all three.
 */
final class TaxTypeNamespaceMigrationTest extends IntegrationTestCase
{
    private const THELIA_2_NAMESPACE = 'Thelia\\TaxEngine\\TaxType\\';

    private const UNTAXED_PRICE = 100.0;

    private Country $country;

    /**
     * @return iterable<string, array{string}>
     */
    public static function scripts(): iterable
    {
        yield 'migration from Thelia 2' => ['3.0.0-alpha1.sql'];
        yield 'repair of a shop already migrated' => ['3.3.0.sql'];
    }

    public function testATaxOnItsThelia2ClassNameCannotBeComputed(): void
    {
        $product = $this->productTaxedBy($this->thelia2Tax(FixAmountTaxType::class, ['amount' => '5']));

        $this->expectException(TaxEngineException::class);
        $this->expectExceptionCode(TaxEngineException::BAD_RECORDED_TYPE);

        $product->getTaxedPrice($this->country, self::UNTAXED_PRICE);
    }

    #[DataProvider('scripts')]
    public function testAPercentageTaxPricesTheProductAgain(string $script): void
    {
        $tax = $this->thelia2Tax(PricePercentTaxType::class, ['percent' => '20']);
        $product = $this->productTaxedBy($tax);

        $this->runTaxStatementsOf($script);

        self::assertSame(PricePercentTaxType::class, $this->storedTypeOf($tax));
        self::assertEqualsWithDelta(120.0, (float) $product->getTaxedPrice($this->country, self::UNTAXED_PRICE), 0.0001);
    }

    #[DataProvider('scripts')]
    public function testAFixedAmountTaxPricesTheProductAgain(string $script): void
    {
        $tax = $this->thelia2Tax(FixAmountTaxType::class, ['amount' => '5']);
        $product = $this->productTaxedBy($tax);

        $this->runTaxStatementsOf($script);

        self::assertSame(FixAmountTaxType::class, $this->storedTypeOf($tax));
        self::assertEqualsWithDelta(105.0, (float) $product->getTaxedPrice($this->country, self::UNTAXED_PRICE), 0.0001);
    }

    /**
     * The eco-tax of a Thelia 2 shop: an amount read from a feature of the product.
     */
    #[DataProvider('scripts')]
    public function testAFeatureAmountTaxPricesTheProductAgain(string $script): void
    {
        $factory = $this->createFixtureFactory();
        $feature = $factory->feature();

        $tax = $this->thelia2Tax(FeatureFixAmountTaxType::class, [
            'feature' => $feature->getId(),
            'lang' => LangQuery::create()->findOneByLocale('en_US')?->getId(),
        ]);
        $product = $this->productTaxedBy($tax);

        (new FeatureProduct())
            ->setProductId($product->getId())
            ->setFeatureId($feature->getId())
            ->setIsFreeText(true)
            ->setFreeTextValue('2.5')
            ->save($this->getPropelConnection());

        $this->runTaxStatementsOf($script);

        self::assertSame(FeatureFixAmountTaxType::class, $this->storedTypeOf($tax));
        self::assertEqualsWithDelta(102.5, (float) $product->getTaxedPrice($this->country, self::UNTAXED_PRICE), 0.0001);
    }

    public function testTheRepairCanBeReplayed(): void
    {
        $tax = $this->thelia2Tax(FeatureFixAmountTaxType::class, ['feature' => 1, 'lang' => 1]);
        $current = $this->createFixtureFactory()->tax();

        $this->runTaxStatementsOf('3.3.0.sql');
        $this->runTaxStatementsOf('3.3.0.sql');

        self::assertSame(FeatureFixAmountTaxType::class, $this->storedTypeOf($tax));
        self::assertSame(PricePercentTaxType::class, $this->storedTypeOf($current));
    }

    /**
     * A tax of the given type, recorded under the class name Thelia 2 gave it.
     *
     * @param class-string         $type
     * @param array<string, mixed> $requirements
     */
    private function thelia2Tax(string $type, array $requirements): Tax
    {
        return $this->createFixtureFactory()->tax([
            'type' => self::THELIA_2_NAMESPACE.substr($type, strrpos($type, '\\') + 1),
            'requirements' => $requirements,
        ]);
    }

    /**
     * A product taxed by the given tax alone, in the country this sets as $this->country.
     */
    private function productTaxedBy(Tax $tax): Product
    {
        $factory = $this->createFixtureFactory();
        $this->country = $factory->country();
        // A non-empty override array forces a rule of its own instead of reusing the seeded one.
        $taxRule = $factory->taxRule(['isDefault' => false]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($this->country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $factory->product($factory->category(), $taxRule, $factory->currency());
    }

    private function storedTypeOf(Tax $tax): ?string
    {
        return TaxQuery::create()->findPk($tax->getId())?->getType();
    }

    private function runTaxStatementsOf(string $script): void
    {
        $connection = Propel::getWriteConnection(TaxTableMap::DATABASE_NAME);

        foreach ($this->taxStatementsOf($script) as $statement) {
            $connection->exec($statement);
        }

        TaxTableMap::clearInstancePool();
    }

    /**
     * The statements of the script that rewrite `tax`.`type`, one per tax type.
     *
     * @return list<string>
     */
    private function taxStatementsOf(string $script): array
    {
        $sql = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.$script);

        $statements = [];

        foreach (explode(';', $sql) as $chunk) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (1 === preg_match('/^UPDATE\s+`tax`\s+SET\s+`type`/i', $statement)) {
                $statements[] = $statement;
            }
        }

        self::assertNotEmpty($statements, \sprintf('%s no longer renames the tax types.', $script));

        return $statements;
    }
}
