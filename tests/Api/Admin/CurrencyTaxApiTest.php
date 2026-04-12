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

use Thelia\Model\CurrencyQuery;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\ApiTestCase;

final class CurrencyTaxApiTest extends ApiTestCase
{
    public function testGetCurrency(): void
    {
        $token = $this->authenticateAsAdmin();
        $currency = $this->createFixtureFactory()->currency();

        $response = $this->jsonRequest('GET', '/api/admin/currencies/'.$currency->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        self::assertSame($currency->getCode(), $data['code']);
    }

    public function testPatchCurrency(): void
    {
        $token = $this->authenticateAsAdmin();
        $currency = $this->createFixtureFactory()->currency();

        $response = $this->jsonRequest('PATCH', '/api/admin/currencies/'.$currency->getId(), [
            'rate' => 1.25,
            'i18ns' => [
                'en_US' => ['title' => 'Updated Currency', 'locale' => 'en_US'],
            ],
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        $reloaded = CurrencyQuery::create()->findPk($currency->getId());
        self::assertEqualsWithDelta(1.25, (float) $reloaded->getRate(), 0.001);
    }

    public function testGetTax(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $tax = $factory->tax();

        $response = $this->jsonRequest('GET', '/api/admin/taxes/'.$tax->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);
    }

    public function testGetTaxRule(): void
    {
        $token = $this->authenticateAsAdmin();
        $taxRule = $this->createFixtureFactory()->taxRule();

        $response = $this->jsonRequest('GET', '/api/admin/tax_rules/'.$taxRule->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);
    }

    public function testDeleteTax(): void
    {
        $token = $this->authenticateAsAdmin();
        $tax = $this->createFixtureFactory()->tax();
        $id = $tax->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/taxes/'.$id, token: $token);
        self::assertSame(204, $response->getStatusCode());
        self::assertNull(TaxQuery::create()->findPk($id));
    }

    public function testDeleteTaxRule(): void
    {
        $token = $this->authenticateAsAdmin();
        $taxRule = $this->createFixtureFactory()->taxRule();
        $id = $taxRule->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/tax_rules/'.$id, token: $token);
        self::assertSame(204, $response->getStatusCode());
        self::assertNull(TaxRuleQuery::create()->findPk($id));
    }
}
