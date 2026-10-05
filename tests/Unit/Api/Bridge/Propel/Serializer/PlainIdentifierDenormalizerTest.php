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

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Serializer;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use PHPUnit\Framework\TestCase;
use Thelia\Api\Bridge\Propel\Serializer\PlainIdentifierDenormalizer;

/**
 * The application serializer asks this denormalizer about every class it reads, API
 * resource or not: a Messenger stamp holding an exception, a value object of a
 * module. A property typed with a union has no single class to be a resource of,
 * and is no reason to stop reading.
 */
final class PlainIdentifierDenormalizerTest extends TestCase
{
    public function testAClassWithAUnionTypedPropertyIsNotForIt(): void
    {
        $resourceClassResolver = $this->createMock(ResourceClassResolverInterface::class);
        $resourceClassResolver->method('isResourceClass')->willReturn(false);

        $denormalizer = new PlainIdentifierDenormalizer($this->createMock(IriConverterInterface::class), $resourceClassResolver);

        self::assertFalse($denormalizer->supportsDenormalization(
            ['code' => 'E42', 'label' => 'refused'],
            UnionTypedValue::class,
            'json',
        ));
    }
}

final class UnionTypedValue
{
    public int|string $code = 0;

    public string $label = '';
}
