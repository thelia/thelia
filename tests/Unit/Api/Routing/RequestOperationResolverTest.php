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

namespace Thelia\Tests\Unit\Api\Routing;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Api\Routing\RequestOperationResolver;

final class RequestOperationResolverTest extends TestCase
{
    public function testTheOperationOnTheRequestIsReturned(): void
    {
        $operation = new Post(name: 'post');
        $request = new Request(attributes: ['_api_operation' => $operation]);

        self::assertSame($operation, $this->resolver()->resolve($request));
    }

    public function testTheOperationIsLookedUpWhenTheRequestCarriesOnlyItsName(): void
    {
        $operation = new Get(name: 'get_one');
        $collection = new ResourceMetadataCollection('Some\\Resource', [new ApiResource(operations: [$operation])]);
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory->method('create')->with('Some\\Resource')->willReturn($collection);

        $request = new Request(attributes: ['_api_resource_class' => 'Some\\Resource', '_api_operation_name' => 'get_one']);

        $found = (new RequestOperationResolver($factory))->resolve($request);

        self::assertSame('get_one', $found->getName());
        self::assertInstanceOf(Get::class, $found);
    }

    public function testAnUnknownOperationNameFindsNothing(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory->method('create')->willReturn(new ResourceMetadataCollection('Some\\Resource', [new ApiResource(operations: [])]));
        $request = new Request(attributes: ['_api_resource_class' => 'Some\\Resource', '_api_operation_name' => 'missing']);

        self::assertNull((new RequestOperationResolver($factory))->find($request));
    }

    public function testARequestOutsideApiPlatformIsRefusedByResolve(): void
    {
        $this->expectException(\LogicException::class);

        $this->resolver()->resolve(Request::create('/somewhere'));
    }

    private function resolver(): RequestOperationResolver
    {
        return new RequestOperationResolver($this->createMock(ResourceMetadataCollectionFactoryInterface::class));
    }
}
