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

namespace Thelia\Tests\Integration\Domain\Taxation;

use Thelia\Domain\Taxation\TaxEngine\TaxType\FeatureFixAmountTaxType;
use Thelia\Model\Feature;
use Thelia\Model\FeatureProduct;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * The eco-tax: an amount read from a feature of the product. The feature holds
 * it as text, in its free value or in the title of the feature value the product
 * is given, and the tax engine adds it to a price as a number.
 */
final class FeatureFixAmountTaxTypeTest extends IntegrationTestCase
{
    public function testTheAmountOfAFreeTextValueIsANumber(): void
    {
        $feature = $this->createFixtureFactory()->feature();
        $product = $this->product();

        (new FeatureProduct())
            ->setProductId($product->getId())
            ->setFeatureId($feature->getId())
            ->setIsFreeText(true)
            ->setFreeTextValue('2.5')
            ->save($this->getPropelConnection());

        self::assertSame(2.5, $this->taxTypeOn($feature)->fixAmountRetriever($product));
    }

    public function testTheAmountOfAFeatureValueIsANumber(): void
    {
        $factory = $this->createFixtureFactory();
        $feature = $factory->feature();
        $value = $factory->featureAv($feature, ['locale' => 'en_US', 'title' => '0.75']);
        $product = $this->product();

        (new FeatureProduct())
            ->setProductId($product->getId())
            ->setFeatureId($feature->getId())
            ->setFeatureAvId($value->getId())
            ->save($this->getPropelConnection());

        self::assertSame(0.75, $this->taxTypeOn($feature)->fixAmountRetriever($product));
        self::assertSame(0.75, $this->taxTypeOn($feature)->calculate($product, 100.0));
    }

    public function testAProductWithoutTheFeatureCarriesNoAmount(): void
    {
        $feature = $this->createFixtureFactory()->feature();

        self::assertSame(0.0, $this->taxTypeOn($feature)->fixAmountRetriever($this->product()));
    }

    private function taxTypeOn(Feature $feature): FeatureFixAmountTaxType
    {
        $type = new FeatureFixAmountTaxType();
        $type->loadRequirements([
            'feature' => $feature->getId(),
            'lang' => LangQuery::create()->findOneByLocale('en_US')?->getId(),
        ]);

        return $type;
    }

    private function product(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
    }
}
