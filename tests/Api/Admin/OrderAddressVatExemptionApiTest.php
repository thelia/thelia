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

use Thelia\Model\CountryQuery;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Test\ApiTestCase;

/**
 * What an exempt order froze on its billing address is the buyer the VAT number was
 * verified for: the API must not rewrite it, any more than the back office does.
 */
final class OrderAddressVatExemptionApiTest extends ApiTestCase
{
    public function testPatchingTheCompanyOfAnExemptOrderAddressLeavesItAsItWas(): void
    {
        $address = $this->orderAddress(exempted: true);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/order_addresses/'.$address->getId(),
            ['company' => 'Somebody Else', 'city' => 'Liège'],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);

        $reloaded = $this->reloaded($address);
        self::assertSame('Liège', $reloaded->getCity(), 'Control: the rest of the patch was applied.');
        self::assertSame('Acme', $reloaded->getCompany());
    }

    public function testPatchingTheCompanyOfAnOrderAddressThatIsNotExemptStillWorks(): void
    {
        $address = $this->orderAddress(exempted: false);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/order_addresses/'.$address->getId(),
            ['company' => 'Somebody Else'],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);
        self::assertSame('Somebody Else', $this->reloaded($address)->getCompany());
    }

    public function testPuttingAnExemptOrderAddressKeepsWhatWasFrozenOnIt(): void
    {
        $address = $this->orderAddress(exempted: true);
        $token = $this->authenticateAsAdmin();

        $current = json_decode(
            (string) $this->jsonRequest('GET', '/api/admin/order_addresses/'.$address->getId(), [], $token)->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        unset($current['@context'], $current['@id'], $current['@type'], $current['id']);
        foreach ($current as $field => $value) {
            $current[$field] = \is_array($value) && isset($value['@id']) ? $value['@id'] : $value;
        }
        $current['city'] = 'Liège';

        $response = $this->jsonRequest('PUT', '/api/admin/order_addresses/'.$address->getId(), $current, $token);

        self::assertJsonResponseSuccessful($response);

        $reloaded = $this->reloaded($address);
        self::assertSame('Liège', $reloaded->getCity(), 'Control: the PUT was applied.');
        self::assertSame('BE0123456789', $reloaded->getVatNumber());
        self::assertNotNull($reloaded->getVatVerifiedAt());
        self::assertSame('Acme SPRL', $reloaded->getVatVerifiedName());
        self::assertEqualsWithDelta(2.0, (float) $reloaded->getVatExemptedAmount(), 0.0001);
        self::assertSame(1, $reloaded->getVatExempted());
    }

    private function orderAddress(bool $exempted): OrderAddress
    {
        $belgium = CountryQuery::create()->findOneByIsoalpha2('BE');
        self::assertNotNull($belgium);

        $address = $this->createFixtureFactory()->orderAddress($belgium, null, ['zipcode' => '1000', 'city' => 'Bruxelles']);

        $this->getPropelConnection()
            ->prepare('UPDATE `order_address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ?, `vat_exempted` = ?, `vat_exempted_amount` = ? WHERE `id` = ?')
            ->execute(['Acme', 'BE0123456789', (new \DateTime('-1 day'))->format('Y-m-d H:i:s'), 'Acme SPRL', $exempted ? 1 : 0, '2.00', $address->getId()]);

        return $this->reloaded($address);
    }

    private function reloaded(OrderAddress $address): OrderAddress
    {
        OrderAddressTableMap::clearInstancePool();
        $reloaded = OrderAddressQuery::create()->findPk($address->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
