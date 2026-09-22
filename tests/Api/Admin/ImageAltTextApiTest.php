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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Product;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * Covers the alt text and decorative flag on product images, both on the
 * multipart upload and on the PATCH endpoint.
 */
final class ImageAltTextApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testUploadingAnImageWithAltAndDecorativePersistsBoth(): void
    {
        $product = $this->createProduct();
        $token = $this->authenticateAsAdmin();

        $this->client->request(
            'POST',
            '/api/admin/product_images',
            parameters: [
                'product' => (string) $product->getId(),
                'decorative' => '1',
                'i18ns' => json_encode([
                    'en_US' => ['alt' => 'A leather bag seen from the front'],
                ], \JSON_THROW_ON_ERROR),
            ],
            files: ['fileToUpload' => $this->createUploadedFile($this->createTestPng(), 'kitten.png', 'image/png')],
            server: [
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $image = ProductImageQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($image);
        $this->trackFileForCleanup($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile());

        self::assertSame(1, (int) $image->getDecorative());
        self::assertSame('A leather bag seen from the front', $image->setLocale('en_US')->getAlt());
    }

    public function testPatchingDecorativeAndAltIsReadBackFromTheDatabase(): void
    {
        $product = $this->createProduct();
        $image = $this->createImage($product);
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/product_images/'.$image->getId(),
            [
                'decorative' => true,
                'i18ns' => [
                    'fr_FR' => ['alt' => 'Un sac en cuir vu de face'],
                ],
            ],
            $token,
            'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);

        $reloaded = ProductImageQuery::create()->findPk($image->getId());
        self::assertSame(1, (int) $reloaded->getDecorative());
        self::assertSame('Un sac en cuir vu de face', $reloaded->setLocale('fr_FR')->getAlt());
    }

    public function testPatchingWithoutDecorativeDoesNotResetTheFlag(): void
    {
        $product = $this->createProduct();
        $image = $this->createImage($product);
        $image->setDecorative(1)->save();
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/product_images/'.$image->getId(),
            [
                'i18ns' => [
                    'fr_FR' => ['alt' => 'Texte alternatif mis à jour'],
                ],
            ],
            $token,
            'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);

        $reloaded = ProductImageQuery::create()->findPk($image->getId());
        self::assertSame(1, (int) $reloaded->getDecorative(), 'A PATCH silent on decorative must not reset it to false.');
    }

    private function createProduct(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );
    }

    private function createImage(Product $product): ProductImage
    {
        $image = new ProductImage();
        $image
            ->setProductId($product->getId())
            ->setLocale('fr_FR')
            ->setTitle('Sac en cuir')
            ->setVisible(1)
            ->setPosition(1)
            ->setFile('sac-en-cuir.png');
        $image->save();

        return $image;
    }
}
