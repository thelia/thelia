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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Map\SaleUnitConfigTableMap;
use Thelia\Model\Map\SaleUnitModuleConfigTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\SaleUnit;
use Thelia\Model\SaleUnitConfigQuery;
use Thelia\Model\SaleUnitModuleConfigQuery;
use Thelia\Test\ApiTestCase;

/**
 * The configuration of a sale unit is written through the admin API: the store
 * settings it sets for itself, and the values it gives the settings of a module, a
 * secret one being written and never read back.
 */
final class SaleUnitConfigApiTest extends ApiTestCase
{
    private SaleUnit $saleUnit;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saleUnit = $this->createFixtureFactory()->saleUnit();
        $this->module = ModuleQuery::create()->findOneByCode('Cheque', $this->getPropelConnection())
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');
    }

    public function testAStoreSettingOfASaleUnitIsCreatedReadChangedAndRemoved(): void
    {
        $token = $this->authenticateAsAdmin();

        $created = $this->jsonRequest('POST', '/api/admin/sale_unit_configs', [
            'saleUnit' => '/api/admin/sale_units/'.$this->saleUnit->getId(),
            'name' => 'store_name',
            'value' => 'Lyon store',
        ], $token);
        self::assertJsonResponseSuccessful($created);
        $id = $this->decode($created)['id'];

        $read = $this->jsonRequest('GET', '/api/admin/sale_unit_configs?saleUnit.id='.$this->saleUnit->getId(), token: $token);
        self::assertJsonResponseSuccessful($read);
        self::assertSame(['store_name' => 'Lyon store'], $this->valuesByName($read));

        $changed = $this->jsonRequest('PATCH', '/api/admin/sale_unit_configs/'.$id, ['value' => 'Lyon city store'], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($changed);
        SaleUnitConfigTableMap::clearInstancePool();
        self::assertSame('Lyon city store', SaleUnitConfigQuery::create()->findPk($id, $this->getPropelConnection())?->getValue());

        $removed = $this->jsonRequest('DELETE', '/api/admin/sale_unit_configs/'.$id, token: $token);
        self::assertSame(204, $removed->getStatusCode(), (string) $removed->getContent());
        SaleUnitConfigTableMap::clearInstancePool();
        self::assertNull(SaleUnitConfigQuery::create()->findPk($id, $this->getPropelConnection()));
    }

    public function testASettingNoSaleUnitMaySetIsRefused(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/sale_unit_configs', [
            'saleUnit' => '/api/admin/sale_units/'.$this->saleUnit->getId(),
            'name' => 'store_country',
            'value' => '64',
        ], $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, SaleUnitConfigQuery::create()->filterBySaleUnitId($this->saleUnit->getId())->count($this->getPropelConnection()));
    }

    public function testASecondValueOfTheSameSettingIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();
        $payload = [
            'saleUnit' => '/api/admin/sale_units/'.$this->saleUnit->getId(),
            'name' => 'store_email',
            'value' => 'lyon@example.com',
        ];

        self::assertJsonResponseSuccessful($this->jsonRequest('POST', '/api/admin/sale_unit_configs', $payload, $token));
        $second = $this->jsonRequest('POST', '/api/admin/sale_unit_configs', $payload, $token);

        self::assertSame(422, $second->getStatusCode(), (string) $second->getContent());
    }

    public function testAModuleSettingOfASaleUnitIsReadBackUnlessItIsSecret(): void
    {
        $token = $this->authenticateAsAdmin();

        $mode = $this->jsonRequest('POST', '/api/admin/sale_unit_module_configs', [
            'saleUnit' => '/api/admin/sale_units/'.$this->saleUnit->getId(),
            'module' => '/api/admin/modules/'.$this->module->getId(),
            'name' => 'mode',
            'value' => 'TEST',
        ], $token);
        self::assertJsonResponseSuccessful($mode);
        self::assertSame('TEST', $this->decode($mode)['value']);
        self::assertFalse($this->decode($mode)['secret']);

        $key = $this->jsonRequest('POST', '/api/admin/sale_unit_module_configs', [
            'saleUnit' => '/api/admin/sale_units/'.$this->saleUnit->getId(),
            'module' => '/api/admin/modules/'.$this->module->getId(),
            'name' => 'secret_key',
            'value' => 'sk_lyon_123',
            'secret' => true,
        ], $token);
        self::assertJsonResponseSuccessful($key);
        $keyId = $this->decode($key)['id'];
        self::assertNull($this->decode($key)['value'] ?? null, 'a secret is not even echoed by the write');
        self::assertStringNotContainsString('sk_lyon_123', (string) $key->getContent());

        $item = $this->jsonRequest('GET', '/api/admin/sale_unit_module_configs/'.$keyId, token: $token);
        self::assertJsonResponseSuccessful($item);
        self::assertNull($this->decode($item)['value'] ?? null);
        self::assertTrue($this->decode($item)['secret']);

        $collection = $this->jsonRequest('GET', '/api/admin/sale_unit_module_configs?saleUnit.id='.$this->saleUnit->getId().'&module.id='.$this->module->getId(), token: $token);
        self::assertJsonResponseSuccessful($collection);
        self::assertSame(['mode' => 'TEST', 'secret_key' => null], $this->valuesByName($collection));
        self::assertStringNotContainsString('sk_lyon_123', (string) $collection->getContent());

        self::assertSame('sk_lyon_123', $this->storedValue($keyId), 'the secret is stored as written');
    }

    public function testChangingAnotherFieldOfASecretKeepsTheStoredValue(): void
    {
        $token = $this->authenticateAsAdmin();
        $keyId = $this->secretKey($token);

        $renamed = $this->jsonRequest('PATCH', '/api/admin/sale_unit_module_configs/'.$keyId, ['name' => 'private_key'], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($renamed);
        self::assertNull($this->decode($renamed)['value'] ?? null);

        self::assertSame('sk_lyon_123', $this->storedValue($keyId));

        $replaced = $this->jsonRequest('PATCH', '/api/admin/sale_unit_module_configs/'.$keyId, ['value' => 'sk_lyon_456'], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($replaced);
        self::assertNull($this->decode($replaced)['value'] ?? null);
        self::assertSame('sk_lyon_456', $this->storedValue($keyId));
    }

    public function testASecretIsNotMadeReadableWithoutBeingReplaced(): void
    {
        $token = $this->authenticateAsAdmin();
        $keyId = $this->secretKey($token);

        $revealed = $this->jsonRequest('PATCH', '/api/admin/sale_unit_module_configs/'.$keyId, ['secret' => false], $token, 'merge-patch+json');
        self::assertSame(422, $revealed->getStatusCode(), (string) $revealed->getContent());
        self::assertStringNotContainsString('sk_lyon_123', (string) $revealed->getContent());

        $read = $this->jsonRequest('GET', '/api/admin/sale_unit_module_configs/'.$keyId, token: $token);
        self::assertNull($this->decode($read)['value'] ?? null);

        $replaced = $this->jsonRequest('PATCH', '/api/admin/sale_unit_module_configs/'.$keyId, ['secret' => false, 'value' => 'pk_lyon_public'], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($replaced);
        self::assertSame('pk_lyon_public', $this->decode($replaced)['value']);
    }

    public function testAnAdministratorWithoutTheRightsIsRefused(): void
    {
        $admin = $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [
            AccessManager::VIEW,
            AccessManager::CREATE,
            AccessManager::UPDATE,
            AccessManager::DELETE,
        ]]);
        $token = $this->authenticateAsAdmin($admin);

        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/sale_unit_configs', token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/sale_unit_configs', [
            'saleUnit' => '/api/admin/sale_units/'.$this->saleUnit->getId(),
            'name' => 'store_name',
            'value' => 'Lyon store',
        ], $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/sale_unit_module_configs', token: $token)->getStatusCode());
        self::assertSame(401, $this->jsonRequest('GET', '/api/admin/sale_unit_module_configs')->getStatusCode());
    }

    private function secretKey(string $token): int
    {
        $key = $this->jsonRequest('POST', '/api/admin/sale_unit_module_configs', [
            'saleUnit' => '/api/admin/sale_units/'.$this->saleUnit->getId(),
            'module' => '/api/admin/modules/'.$this->module->getId(),
            'name' => 'secret_key',
            'value' => 'sk_lyon_123',
            'secret' => true,
        ], $token);
        self::assertJsonResponseSuccessful($key);

        return $this->decode($key)['id'];
    }

    private function storedValue(int $id): ?string
    {
        SaleUnitModuleConfigTableMap::clearInstancePool();

        return SaleUnitModuleConfigQuery::create()->findPk($id, $this->getPropelConnection())?->getValue();
    }

    /**
     * A null value is left out of the payload, as every null of the API is.
     *
     * @return array<string, string|null>
     */
    private function valuesByName(Response $response): array
    {
        $values = [];

        foreach ($this->decode($response)['hydra:member'] as $member) {
            $values[$member['name']] = $member['value'] ?? null;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
