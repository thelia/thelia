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

namespace Thelia\Tests\Integration\Company;

use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\TheliaKernel;
use Thelia\Domain\Company\CompanyPermissionProviderInterface;
use Thelia\Domain\Company\Service\CompanyPermissionRegistry;
use Thelia\Test\IntegrationTestCase;

/**
 * A module declares the permissions it adds by implementing the interface: no tag to write, no
 * compiler pass to join.
 */
final class CompanyPermissionAutoconfigurationTest extends IntegrationTestCase
{
    public function testAServiceThatImplementsTheProviderInterfaceIsTaggedForTheRegistry(): void
    {
        $container = new ContainerBuilder();

        $method = new \ReflectionMethod(TheliaKernel::class, 'loadAutoConfigureInterfaces');
        $method->invoke(static::$kernel, $container);

        $definition = (new Definition(ModuleCompanyPermissions::class))->setAutoconfigured(true);
        $container->setDefinition('a.module.provider', $definition);

        (new ResolveInstanceofConditionalsPass())->process($container);

        self::assertArrayHasKey('thelia.company.permission_provider', $container->getDefinition('a.module.provider')->getTags());
    }

    public function testTheRegistryOfTheShopListsTheCorePermissions(): void
    {
        $registry = static::getContainer()->get(CompanyPermissionRegistry::class);

        self::assertInstanceOf(CompanyPermissionRegistry::class, $registry);
        self::assertTrue($registry->isKnown('buyer.manage'));
    }
}

/**
 * @internal
 */
final class ModuleCompanyPermissions implements CompanyPermissionProviderInterface
{
    public function permissions(): array
    {
        return ['quote.request' => 'Request a quote'];
    }
}
