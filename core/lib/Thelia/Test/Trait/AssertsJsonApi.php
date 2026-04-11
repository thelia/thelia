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

namespace Thelia\Test\Trait;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assertions helpers for API Platform responses.
 *
 * Supports both the "simple array" format used by Thelia when no
 * Hydra context is requested, and the full `jsonld` format that
 * carries `hydra:member` / `hydra:totalItems`.
 */
trait AssertsJsonApi
{
    /**
     * @return array<string, mixed>
     */
    protected static function decodeJson(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    protected static function assertJsonResponseSuccessful(Response $response): void
    {
        Assert::assertSame(
            true,
            $response->isSuccessful(),
            \sprintf('Expected a 2xx response, got %d. Body: %s', $response->getStatusCode(), $response->getContent()),
        );

        $contentType = $response->headers->get('Content-Type', '');
        Assert::assertMatchesRegularExpression(
            '#^application/(ld\+)?json\b#',
            $contentType,
            \sprintf('Expected a JSON response, got Content-Type: %s', $contentType),
        );
    }

    protected static function assertJsonCollectionHasCount(int $expected, Response $response): void
    {
        $payload = self::decodeJson($response);

        $members = \array_key_exists('hydra:member', $payload)
            ? $payload['hydra:member']
            : $payload;

        Assert::assertIsArray($members, 'Expected a collection payload.');
        Assert::assertCount($expected, $members);
    }

    protected static function assertHydraTotalItems(int $expected, Response $response): void
    {
        $payload = self::decodeJson($response);

        Assert::assertArrayHasKey(
            'hydra:totalItems',
            $payload,
            'Response is not JSON-LD (missing hydra:totalItems). Request with Accept: application/ld+json.',
        );
        Assert::assertSame($expected, $payload['hydra:totalItems']);
    }

    protected static function assertResourceId(int $expected, Response $response): void
    {
        $payload = self::decodeJson($response);

        Assert::assertArrayHasKey('id', $payload, 'Response has no "id" field.');
        Assert::assertSame($expected, $payload['id']);
    }
}
