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

namespace Thelia\Tests\Api\Admin;

use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;

/**
 * A catalog write made through the admin API never reaches the back-office
 * actions, so the rules have to hear of it another way: a product moved into a
 * rule's scope through the API is priced by it in the same request.
 */
final class CatalogPriceRuleResourceSyncTest extends ApiTestCase
{
    public function testAProductChangedThroughTheApiIsReEvaluatedAgainstTheRules(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $brand = $factory->brand();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency, ['basePrice' => 100.0]);
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();

        $rule = $factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_BRAND, $brand->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $reader = $this->getService(PublicPriceReader::class);
        self::assertSame([], $reader->currentPrices([$pse->getId()], $currency), 'not of the brand yet');

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'i18ns' => ['en_US' => ['title' => 'Now of the brand', 'locale' => 'en_US']],
            'brand' => '/api/admin/brands/'.$brand->getId(),
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertEqualsWithDelta(80.0, $reader->currentPrices([$pse->getId()], $currency)[$pse->getId()]->untaxedPrice, 0.000001);
    }
}
