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

use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thelia\Api\Service\API\NormalizerService;
use Thelia\Api\Service\API\ResourceService;
use Thelia\Model\ProductImage;
use Thelia\Test\IntegrationTestCase;

/**
 * API Platform keys its attribute cache on serialize() of the normalization context, once for every item of a
 * collection (AbstractItemNormalizer::getCacheKey()). ResourceService puts the result itself (extra_variables) and the
 * request into that context: unless they are left out of the key, every item serializes the whole collection, its
 * Propel models included, and a page of 60 products spends seconds there. What the key serializes must not grow with
 * the collection.
 */
final class DataAccessSerializerCacheKeyTest extends IntegrationTestCase
{
    public function testThePartOfTheContextTheSerializerKeysOnDoesNotGrowWithTheCollection(): void
    {
        $serializer = static::getContainer()->get('serializer');
        \assert($serializer instanceof NormalizerInterface);
        $spy = new readonly class($serializer, new \ArrayObject()) extends NormalizerService {
            /** @param \ArrayObject<int, array<string, mixed>> $contexts the contexts of the normalizations */
            public function __construct(NormalizerInterface $normalizer, public \ArrayObject $contexts)
            {
                parent::__construct($normalizer);
            }

            public function normalizeData(object|array $data, array $context, ?string $format = null): array
            {
                $this->contexts->append($context);

                return parent::normalizeData($data, $context, $format);
            }
        };
        $resources = self::withNormalizer(static::getContainer()->get(ResourceService::class), $spy);

        $few = $this->keyedContextLength($resources, $spy->contexts, 2);
        $many = $this->keyedContextLength($resources, $spy->contexts, 8);

        self::assertSame($few, $many, \sprintf('the cache key serializes %d bytes for 2 products, %d for 8', $few, $many));
    }

    /**
     * @param \ArrayObject<int, array<string, mixed>> $contexts the contexts the spy records
     */
    private function keyedContextLength(ResourceService $resources, \ArrayObject $contexts, int $count): int
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        for ($index = 1; $index <= $count; ++$index) {
            $product = $factory->product($category, $taxRule, $currency, ['basePrice' => 10.0 * $index]);
            $product->setLocale('en_US')->setTitle('Product '.$index)->save();
            $image = new ProductImage();
            $image->setProductId($product->getId())->setVisible(1)->setPosition(1);
            $image->setLocale('en_US')->setTitle('Product '.$index)->setFile('cache-key-test-'.$product->getId().'.png');
            $image->save();
        }

        $contexts->exchangeArray([]);
        $payload = $resources->resources('/api/front/products', ['tfilters' => ['category' => [[$category->getId()]]], 'itemsPerPage' => 50], 'jsonld');

        self::assertIsArray($payload);
        self::assertCount($count, $payload['hydra:member'] ?? [], 'the collection holds the products of its category');
        self::assertCount(1, $contexts, 'one normalization for the collection');

        return \strlen(serialize(self::keyedPart($contexts[0])));
    }

    /**
     * The context as AbstractItemNormalizer::getCacheKey() serializes it (API Platform's CacheKeyTrait).
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function keyedPart(array $context): array
    {
        foreach ($context[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY] ?? [] as $key) {
            unset($context[$key]);
        }

        unset($context[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY], $context[AbstractObjectNormalizer::OBJECT_TO_POPULATE], $context['cache_key']);

        return $context;
    }

    /**
     * The service of the container with another normalizer: the one of the container is built with the kernel, before a
     * test can replace it.
     */
    private static function withNormalizer(ResourceService $service, NormalizerService $normalizer): ResourceService
    {
        $class = new \ReflectionClass($service);
        $arguments = [];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $arguments[] = NormalizerService::class === (string) $parameter->getType()
                ? $normalizer
                : $class->getProperty($parameter->getName())->getValue($service);
        }

        $copy = $class->newInstanceArgs($arguments);
        \assert($copy instanceof ResourceService);

        return $copy;
    }
}
