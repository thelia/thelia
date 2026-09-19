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
use Thelia\Model\CatalogPriceRule;
use Thelia\Test\ApiTestCase;

/**
 * The rules are readable by an integration, with their definition and coverage;
 * they are not writable through the API.
 */
final class CatalogPriceRuleApiTest extends ApiTestCase
{
    public function testARuleIsReadWithItsDefinitionAndCoverage(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $customer = $factory->customer($factory->customerTitle());

        $rule = $factory->catalogPriceRule(['active' => true, 'effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'title' => 'API rule']);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $factory->catalogPriceRuleEffectCurrency($rule, $currency, 12.5);
        $factory->catalogPriceRuleCustomer($rule, $customer);
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $response = $this->jsonRequest('GET', '/api/admin/catalog_price_rules/'.$rule->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame($rule->getId(), $payload['id']);
        self::assertTrue($payload['active']);
        self::assertSame('running', $payload['state']);
        self::assertSame(1, $payload['affectedProductCount']);
        self::assertSame([CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]], $payload['criteria']);
        self::assertEqualsWithDelta(12.5, $payload['effectValuesByCurrency'][$currency->getId()], 0.0001);
        self::assertSame([$customer->getId()], $payload['customerIds']);
        self::assertSame('API rule', $payload['i18ns']['en_US']['title']);

        $collection = $this->jsonRequest('GET', '/api/admin/catalog_price_rules?id='.$rule->getId(), token: $token);
        self::assertJsonResponseSuccessful($collection);
        $members = json_decode((string) $collection->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'];
        self::assertCount(1, $members);
        self::assertSame(1, $members[0]['affectedProductCount']);
        self::assertArrayNotHasKey('criteria', $members[0], 'the definition travels with the single read only');
    }

    public function testARuleIsNotWritableThroughTheApi(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/catalog_price_rules', ['active' => true], $token);

        self::assertContains($response->getStatusCode(), [404, 405], $response->getContent());
    }
}
