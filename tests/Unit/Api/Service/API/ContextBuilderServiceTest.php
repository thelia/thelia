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

namespace Thelia\Tests\Unit\Api\Service\API;

use ApiPlatform\Metadata\GetCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Thelia\Api\Service\API\ContextBuilderService;
use Thelia\Api\Service\API\ResourceService;

/**
 * The serializer keys its attribute cache on a serialization of the context, once for every item of a collection.
 * Whatever ResourceService adds to the context after building it (the result, the request) must stay out of that key,
 * or every item serializes the whole result again.
 */
final class ContextBuilderServiceTest extends TestCase
{
    public function testEveryKeyResourceServiceAddsIsLeftOutOfTheSerializerCacheKey(): void
    {
        $excluded = $this->context()[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY] ?? [];
        $added = $this->keysResourceServiceAdds();

        self::assertContains('extra_variables', $added, 'the source of ResourceService is read as expected');
        self::assertContains('request', $added, 'the source of ResourceService is read as expected');

        foreach ($added as $key) {
            self::assertContains($key, $excluded, \sprintf('"%s" is added to the context by ResourceService and must not be serialized into the cache key of every item.', $key));
        }
    }

    public function testTheKeysThatChooseTheAttributesStayInTheCacheKey(): void
    {
        $excluded = $this->context()[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY] ?? [];

        foreach (['groups', 'resource_class', 'filters', 'path_info', 'uri_variables'] as $key) {
            self::assertNotContains($key, $excluded);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return (new ContextBuilderService())->buildContext(
            '/api/front/products',
            new GetCollection(normalizationContext: ['groups' => ['product:read']]),
            'Thelia\Api\Resource\Product',
            [],
            [],
        );
    }

    /**
     * The keys ResourceService writes into the context it got from the builder (`$context['key'] = ...` or
     * `$context['key'][...] = ...`), read from its source: a key added there later is checked without touching this test.
     *
     * @return list<string>
     */
    private function keysResourceServiceAdds(): array
    {
        $file = (new \ReflectionClass(ResourceService::class))->getFileName();
        self::assertIsString($file);
        preg_match_all('/\$context\[\'([A-Za-z_]+)\'\](?:\[[^\]]*\])*\s*=(?!=)/', (string) file_get_contents($file), $matches);

        return array_values(array_unique($matches[1]));
    }
}
