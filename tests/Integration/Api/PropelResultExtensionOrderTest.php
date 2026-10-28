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

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Api\Bridge\Propel\Extension\PaginationExtension;
use Thelia\Api\Bridge\Propel\Extension\ProductPriceSortExtension;
use Thelia\Api\Bridge\Propel\Extension\QueryResultCollectionExtensionInterface;
use Thelia\Api\Bridge\Propel\State\PropelCollectionProvider;
use Thelia\Test\IntegrationTestCase;

/**
 * The collection provider serves the first result extension that answers, in the order of the
 * container: one that has the whole collection to itself comes before the pagination one, which
 * answers for every paginated collection, and a module's extension, at the default priority, is
 * asked before it too.
 */
final class PropelResultExtensionOrderTest extends IntegrationTestCase
{
    public function testAModuleExtensionAtTheDefaultPriorityIsServedBeforeThePagination(): void
    {
        $container = new ContainerBuilder();
        // Registered first: with equal priorities it would come first.
        $container->register('pagination', PaginationExtension::class)->setArgument('$pagination', null)->addTag('extension');
        $container->register('module', ModuleResultExtensionAtTheDefaultPriority::class)->addTag('extension');
        $container->register('holder', ExtensionsHolder::class)
            ->setArgument('$extensions', new TaggedIteratorArgument('extension', null, null, false, 'getDefaultPriority'))
            ->setPublic(true);
        $container->compile();

        /** @var ExtensionsHolder $holder */
        $holder = $container->get('holder');

        self::assertSame(
            [ModuleResultExtensionAtTheDefaultPriority::class, PaginationExtension::class],
            array_map(static fn (object $extension): string => $extension::class, iterator_to_array($holder->extensions, false)),
        );
    }

    public function testThePaginationIsTheLastOfTheExtensionsTheProviderIsGiven(): void
    {
        $provider = $this->getService(PropelCollectionProvider::class);
        $extensions = array_values(iterator_to_array((new \ReflectionProperty($provider, 'propelCollectionExtensions'))->getValue($provider), false));
        $classes = array_map(static fn (object $extension): string => $extension::class, $extensions);

        self::assertSame(PaginationExtension::class, end($classes));
        self::assertLessThan(array_search(PaginationExtension::class, $classes, true), array_search(ProductPriceSortExtension::class, $classes, true));
    }
}

final class ExtensionsHolder
{
    /**
     * @param iterable<object> $extensions
     */
    public function __construct(public readonly iterable $extensions)
    {
    }
}

final class ModuleResultExtensionAtTheDefaultPriority implements QueryResultCollectionExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
    }

    public function supportsResult(string $resourceClass, ?Operation $operation = null, array $context = []): bool
    {
        return true;
    }

    public function getResult(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = [])
    {
        return null;
    }
}
