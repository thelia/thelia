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

namespace Thelia\Test;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Test\Trait\AssertsJsonApi;
use Thelia\Test\Trait\LogsInAsAdmin;
use Thelia\Test\Trait\LogsInAsCustomer;

/**
 * Base class for API Platform HTTP tests.
 *
 * Inherits transaction rollback and kernel setup from
 * {@see WebIntegrationTestCase}, and composes JWT login helpers and
 * JSON-LD assertions.
 *
 * Typical usage:
 *
 *   final class ProductApiTest extends ApiTestCase
 *   {
 *       public function testListProducts(): void
 *       {
 *           $token = $this->authenticateAsAdmin();
 *           $response = $this->jsonRequest('GET', '/api/admin/products', token: $token);
 *
 *           self::assertJsonResponseSuccessful($response);
 *           self::assertHydraTotalItems(0, $response);
 *       }
 *   }
 */
abstract class ApiTestCase extends WebIntegrationTestCase
{
    use AssertsJsonApi;
    use LogsInAsAdmin;
    use LogsInAsCustomer;

    /**
     * Map of short format keys to their Content-Type / Accept MIME type.
     */
    private const MIME_TYPES = [
        'jsonld' => 'application/ld+json',
        'json' => 'application/json',
        'merge-patch+json' => 'application/merge-patch+json',
    ];

    /**
     * Sends a JSON request and returns the underlying Response.
     *
     * @param array<string, mixed> $payload encoded as JSON body if non-empty
     * @param string               $format  'jsonld' (default) or 'json'
     */
    protected function jsonRequest(
        string $method,
        string $uri,
        array $payload = [],
        ?string $token = null,
        string $format = 'jsonld',
    ): Response {
        $mimeType = self::MIME_TYPES[$format]
            ?? throw new \InvalidArgumentException(\sprintf('Unsupported format "%s".', $format));

        // For merge-patch+json, the Content-Type is the patch format but
        // the Accept header must remain a standard format (jsonld).
        $acceptType = 'application/merge-patch+json' === $mimeType
            ? self::MIME_TYPES['jsonld']
            : $mimeType;

        $server = [
            'CONTENT_TYPE' => $mimeType,
            'HTTP_ACCEPT' => $acceptType,
        ];

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: [] === $payload ? null : json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }
}
