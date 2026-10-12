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

namespace Thelia\Tests\Integration\Domain\Catalog\Product;

use Thelia\Domain\Catalog\Product\DTO\ProductWithPSECreateDTO;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * {@see ProductFacade::createWithPSE()} creates a product and fills its default
 * combination in one call. The product takes the shop's default tax rule when the
 * caller names none, as a product created on its own does.
 */
final class ProductCreateWithCombinationTest extends ActionIntegrationTestCase
{
    public function testAProductCreatedWithoutATaxRuleKeepsTheDefaultOne(): void
    {
        $defaultTaxRule = TaxRuleQuery::create()->findOneByIsDefault(true);
        self::assertNotNull($defaultTaxRule, 'The test database is seeded with a default tax rule.');
        $category = $this->factory->category();
        $currency = $this->factory->currency();

        $product = $this->getService(ProductFacade::class)->createWithPSE(new ProductWithPSECreateDTO(
            ref: 'KETTLE-'.uniqid(),
            title: 'Kettle',
            locale: 'en_US',
            defaultCategoryId: $category->getId(),
            price: 19.9,
            currencyId: $currency->getId(),
        ));

        self::assertSame($defaultTaxRule->getId(), ProductQuery::create()->findPk($product->getId())?->getTaxRuleId());
    }

    public function testATaxRuleGivenByTheCallerIsKept(): void
    {
        $chosenTaxRule = $this->factory->taxRule(['isDefault' => false]);
        $category = $this->factory->category();
        $currency = $this->factory->currency();

        $product = $this->getService(ProductFacade::class)->createWithPSE(new ProductWithPSECreateDTO(
            ref: 'KETTLE-'.uniqid(),
            title: 'Kettle',
            locale: 'en_US',
            defaultCategoryId: $category->getId(),
            price: 19.9,
            currencyId: $currency->getId(),
            taxRuleId: $chosenTaxRule->getId(),
        ));

        self::assertSame($chosenTaxRule->getId(), ProductQuery::create()->findPk($product->getId())?->getTaxRuleId());
    }
}
