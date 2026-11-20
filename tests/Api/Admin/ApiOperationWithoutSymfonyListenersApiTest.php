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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * A project that does not turn "use_symfony_listeners" on reaches the upload controllers
 * with no "_api_operation" on the request: the upload used to answer 500 after the image
 * was stored, so a client that retried created duplicates.
 */
final class ApiOperationWithoutSymfonyListenersApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    private \Closure $dropOperation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropOperation = static function (ControllerEvent $event): void {
            $event->getRequest()->attributes->remove('_api_operation');
        };
        static::getContainer()->get(EventDispatcherInterface::class)->addListener(KernelEvents::CONTROLLER, $this->dropOperation, -1000);
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(EventDispatcherInterface::class)->removeListener(KernelEvents::CONTROLLER, $this->dropOperation);
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testAnImageIsUploadedWhenTheRequestCarriesNoOperation(): void
    {
        $product = $this->createFixtureFactory()->product(
            $this->createFixtureFactory()->category(),
            $this->createFixtureFactory()->taxRule(),
            $this->createFixtureFactory()->currency(),
        );

        $response = $this->upload('/api/admin/product_images', ['product' => (string) $product->getId()], 'kitten.png');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $body);

        $images = ProductImageQuery::create()->filterByProductId($product->getId())->find();
        self::assertCount(1, $images);
        self::assertSame((int) $body['id'], $images->getFirst()->getId());
        $this->trackFileForCleanup($images->getFirst()->getUploadDir().\DIRECTORY_SEPARATOR.$images->getFirst()->getFile());
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(string $uri, array $fields, string $fileName): Response
    {
        $this->client->request(
            'POST',
            $uri,
            parameters: $fields,
            files: ['fileToUpload' => $this->createUploadedFile($this->createTestPng(), $fileName, 'image/png')],
            server: [
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->authenticateAsAdmin(),
            ],
        );

        return $this->client->getResponse();
    }
}
