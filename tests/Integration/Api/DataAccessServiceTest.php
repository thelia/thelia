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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsAdmin;

/**
 * Guard-rail for {@see DataAccessService::resources()}, which calls
 * `api_platform.state_provider.locator` (CallableProvider) directly to
 * bypass the HTTP stack. If a future API Platform release renames or
 * restructures that locator, the front (Flexy) goes silent in prod.
 *
 * This test exercises the full pipeline (route match → operation
 * resolution → state provider invocation → normalization) on stable
 * native resources so the breakage surfaces in CI before deploy.
 */
final class DataAccessServiceTest extends IntegrationTestCase
{
    use LogsInAsAdmin;

    private DataAccessService $dataAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataAccess = static::getContainer()->get(DataAccessService::class);
    }

    public function testFrontProductsCollectionResolvesWithoutError(): void
    {
        $result = $this->dataAccess->resources('/api/front/products');

        self::assertIsArray($result);
    }

    public function testFrontCategoriesCollectionResolvesWithoutError(): void
    {
        $result = $this->dataAccess->resources('/api/front/categories');

        self::assertIsArray($result);
    }

    public function testAdminCategoriesCollectionResolvesWithoutError(): void
    {
        $this->loginAsAdminInSession();

        $result = $this->dataAccess->resources('/api/admin/categories');

        self::assertIsArray($result);
    }

    public function testAdminProductsCollectionResolvesWithoutError(): void
    {
        $this->loginAsAdminInSession();

        $result = $this->dataAccess->resources('/api/admin/products');

        self::assertIsArray($result);
    }
}
