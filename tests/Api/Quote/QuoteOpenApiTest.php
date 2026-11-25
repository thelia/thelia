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

namespace Thelia\Tests\Api\Quote;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Thelia\Api\Resource\Quote;
use Thelia\Api\Resource\QuoteItem;
use Thelia\Api\Resource\QuoteRevision;
use Thelia\Test\ApiTestCase;

/**
 * The operations the quotes publish, and what each declares: no more than the ones the facade serves,
 * none that writes a state or a price, and a security expression on every one of the storefront.
 */
final class QuoteOpenApiTest extends ApiTestCase
{
    private const array EXPECTED = [
        'get /api/admin/quotes',
        'delete /api/admin/quotes/{id}',
        'get /api/admin/quotes/{id}',
        'get /api/admin/quotes/{id}/revisions',
        'get /api/admin/quotes/{id}/revisions/{number}',
        'get /api/admin/quotes/{id}/revisions/{number}/items',
        'get /api/admin/quotes/{id}/revisions/{number}/items/{itemId}',
        'get /api/front/account/quotes',
        'get /api/front/account/quotes/{id}',
        'get /api/front/account/quotes/{id}/revisions',
        'get /api/front/account/quotes/{id}/revisions/{number}',
        'post /api/admin/quotes',
        'post /api/admin/quotes/{id}/extend',
        'post /api/admin/quotes/{id}/remind',
        'post /api/admin/quotes/{id}/send',
        'post /api/front/account/quotes',
        'post /api/front/account/quotes/{id}/accept',
        'post /api/front/account/quotes/{id}/counter-offer',
        'post /api/front/account/quotes/{id}/reject',
        'put /api/admin/quotes/{id}/draft',
    ];

    public function testTheDocumentationHoldsTheQuoteOperationsAndNoOther(): void
    {
        $documentation = $this->documentation();
        $found = [];

        foreach ($documentation['paths'] as $path => $operations) {
            if (!str_contains((string) $path, 'quote')) {
                continue;
            }

            foreach (array_keys($operations) as $method) {
                if (\in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $found[] = $method.' '.$path;
                }
            }
        }

        sort($found);
        $expected = self::EXPECTED;
        sort($expected);

        self::assertSame($expected, $found);
    }

    public function testTheOperationsDocumentTheirBodyAndTheirFilters(): void
    {
        $paths = $this->documentation()['paths'];

        foreach (['/api/front/account/quotes', '/api/front/account/quotes/{id}/counter-offer', '/api/front/account/quotes/{id}/accept', '/api/front/account/quotes/{id}/reject', '/api/admin/quotes', '/api/admin/quotes/{id}/send'] as $path) {
            $operation = $paths[$path]['post'];
            if (!str_ends_with($path, '/send')) {
                self::assertArrayHasKey('application/json', $operation['requestBody']['content'], $path);
            }
        }

        self::assertSame(['revision'], array_keys($paths['/api/front/account/quotes/{id}/accept']['post']['requestBody']['content']['application/json']['schema']['properties']), 'An acceptance names a revision and nothing else.');

        $filters = array_column($paths['/api/admin/quotes']['get']['parameters'], 'name');
        foreach (['state', 'customerId', 'companyId', 'validUntil[before]', 'validUntil[after]', 'ref'] as $name) {
            self::assertContains($name, $filters);
        }
    }

    public function testEveryStorefrontOperationDeclaresItsSecurityAndNoneWritesAStateOrAPrice(): void
    {
        $factory = static::getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);
        $operations = [];

        foreach ($factory->create(Quote::class) as $resource) {
            foreach ($resource->getOperations() as $operation) {
                \assert($operation instanceof HttpOperation);
                $operations[] = $operation;
            }
        }

        // The revisions and the lines are resources of their own, which read and nothing else.
        self::assertCount(\count(array_filter(self::EXPECTED, static fn (string $operation): bool => !str_contains($operation, '/revisions'))), $operations);

        foreach ($operations as $operation) {
            $template = (string) $operation->getUriTemplate();

            self::assertNotSame('PATCH', $operation->getMethod(), $template);

            if ('DELETE' === $operation->getMethod()) {
                self::assertSame('/admin/quotes/{id}', $template, 'the administration deletes a quote and the storefront deletes nothing');
            }

            if (str_starts_with($template, '/front/account/')) {
                self::assertStringContainsString('ROLE_CUSTOMER', (string) $operation->getSecurity(), $operation->getMethod().' '.$template.' states who may call it.');
                self::assertNotSame('PUT', $operation->getMethod(), $template);
            }
        }
    }

    public function testTheRevisionsAndTheLinesOnlyReadAndTheStorefrontReadsTheRevisionsOnly(): void
    {
        $factory = static::getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);
        $templates = [];

        foreach ([QuoteRevision::class, QuoteItem::class] as $class) {
            foreach ($factory->create($class) as $resource) {
                foreach ($resource->getOperations() as $operation) {
                    \assert($operation instanceof HttpOperation);

                    // The route API Platform adds to name a line by its address answers 404 and nothing else.
                    if ($operation instanceof NotExposed) {
                        continue;
                    }

                    self::assertSame('GET', $operation->getMethod(), $class.' '.$operation->getUriTemplate());
                    $template = (string) $operation->getUriTemplate();

                    if (str_starts_with($template, '/front/account/quotes/')) {
                        self::assertSame(QuoteRevision::class, $class, 'the storefront reads the revisions and nothing below them');
                        self::assertStringContainsString('ROLE_CUSTOMER', (string) $operation->getSecurity(), $template.' states who may call it.');
                    } else {
                        self::assertStringStartsWith('/admin/quotes/', $template);
                    }

                    $templates[] = $template;
                }
            }
        }

        sort($templates);
        self::assertSame([
            '/admin/quotes/{id}/revisions',
            '/admin/quotes/{id}/revisions/{number}',
            '/admin/quotes/{id}/revisions/{number}/items',
            '/admin/quotes/{id}/revisions/{number}/items/{itemId}',
            '/front/account/quotes/{id}/revisions',
            '/front/account/quotes/{id}/revisions/{number}',
        ], $templates);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentation(): array
    {
        $this->client->request('GET', '/api/docs.jsonopenapi', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
