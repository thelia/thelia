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

namespace Thelia\Tests\Unit\Domain\Company;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyPermissionProviderInterface;
use Thelia\Domain\Company\Service\CompanyPermissionRegistry;

final class CompanyPermissionRegistryTest extends TestCase
{
    public function testItListsTheCorePermissionsAndTheOnesOfTheModules(): void
    {
        $registry = new CompanyPermissionRegistry([
            $this->provider(['quote.request' => 'Request a quote']),
            $this->provider(['catalog.export' => 'Export the catalog']),
        ]);

        $all = $registry->all();

        foreach ([
            CompanyPermission::ORDER_PLACE,
            CompanyPermission::ORDER_VIEW_OWN,
            CompanyPermission::ORDER_VIEW_COMPANY,
            CompanyPermission::ORDER_APPROVE,
            CompanyPermission::ADDRESS_MANAGE_SHARED,
            CompanyPermission::BUYER_MANAGE,
            CompanyPermission::COMPANY_EDIT,
        ] as $core) {
            self::assertArrayHasKey($core, $all);
        }

        self::assertSame('Request a quote', $all['quote.request']);
        self::assertSame('Export the catalog', $all['catalog.export']);
        self::assertTrue($registry->isKnown('quote.request'));
        self::assertFalse($registry->isKnown('nothing.at.all'));
    }

    public function testAModuleCannotRedefineACorePermissionAndTheFirstModuleKeepsItsCode(): void
    {
        $registry = new CompanyPermissionRegistry([
            $this->provider([CompanyPermission::BUYER_MANAGE => 'Everything', 'quote.request' => 'First']),
            $this->provider(['quote.request' => 'Second']),
        ]);

        self::assertSame(CompanyPermission::labels()[CompanyPermission::BUYER_MANAGE], $registry->all()[CompanyPermission::BUYER_MANAGE]);
        self::assertSame('First', $registry->all()['quote.request']);
    }

    public function testWithoutAnyModuleOnlyTheCorePermissionsAreKnown(): void
    {
        self::assertSame(CompanyPermission::labels(), (new CompanyPermissionRegistry([]))->all());
    }

    /**
     * @param array<string, string> $permissions
     */
    private function provider(array $permissions): CompanyPermissionProviderInterface
    {
        return new readonly class($permissions) implements CompanyPermissionProviderInterface {
            /**
             * @param array<string, string> $permissions
             */
            public function __construct(private array $permissions)
            {
            }

            public function permissions(): array
            {
                return $this->permissions;
            }
        };
    }
}
