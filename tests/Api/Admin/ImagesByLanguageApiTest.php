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

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\ProductImageI18nTableMap;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * Reading and writing the file of one language of an image over the API.
 */
final class ImagesByLanguageApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    private string $defaultLocale;

    private string $otherLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = Lang::getDefaultLanguage()->getLocale();
        $this->otherLocale = (string) LangQuery::create()->filterByByDefault(0)->filterByActive(1)->orderByLocale()->findOne()?->getLocale();
        self::assertNotSame('', $this->otherLocale, 'The test database has a single active language.');
    }

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testACreatedImageStoresItsFileInTheLanguageTheRequestNames(): void
    {
        $product = $this->createProduct();

        $response = $this->upload('/api/admin/product_images', ['product' => (string) $product->getId(), 'locale' => $this->otherLocale], 1, 'other.png');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $image = $this->reload(ProductImageQuery::create()->findOneByProductId($product->getId()));
        self::assertNotNull($image->setLocale($this->otherLocale)->getOwnFile());
        self::assertNull($image->setLocale($this->defaultLocale)->getOwnFile());
    }

    public function testAnUnknownLocaleIsRefused(): void
    {
        $product = $this->createProduct();

        $response = $this->upload('/api/admin/product_images', ['product' => (string) $product->getId(), 'locale' => 'xx_XX'], 1, 'unknown.png');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductImageQuery::create()->findOneByProductId($product->getId()));
    }

    public function testEachLanguageReadsAndServesItsOwnFile(): void
    {
        $product = $this->createProduct();
        $created = $this->upload('/api/admin/product_images', ['product' => (string) $product->getId()], 1, 'default.png');
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $imageId = (int) json_decode((string) $created->getContent(), true)['id'];

        $replaced = $this->upload('/api/admin/product_images/'.$imageId.'/file', ['locale' => $this->otherLocale], 2, 'other.png');
        self::assertSame(Response::HTTP_OK, $replaced->getStatusCode(), (string) $replaced->getContent());

        $image = $this->reload(ProductImageQuery::create()->findPk($imageId));
        $defaultFile = (string) $image->setLocale($this->defaultLocale)->getOwnFile();
        $otherFile = (string) $image->setLocale($this->otherLocale)->getOwnFile();
        self::assertNotSame('', $otherFile);
        self::assertNotSame($defaultFile, $otherFile);
        self::assertSame($otherFile, json_decode((string) $replaced->getContent(), true)['file'], 'The answer reads the language just written.');

        $token = $this->authenticateAsAdmin();

        $read = json_decode((string) $this->jsonRequest('GET', '/api/admin/product_images/'.$imageId, token: $token)->getContent(), true);
        self::assertSame($defaultFile, $read['file']);
        self::assertSame($defaultFile, $read['i18ns'][$this->defaultLocale]['file']);
        self::assertSame($otherFile, $read['i18ns'][$this->otherLocale]['file']);

        $readInOtherLocale = json_decode((string) $this->jsonRequest('GET', '/api/admin/product_images/'.$imageId.'?locale='.$this->otherLocale, token: $token)->getContent(), true);
        self::assertSame($otherFile, $readInOtherLocale['file']);

        self::assertSame($image->getUploadDir().\DIRECTORY_SEPARATOR.$defaultFile, $this->servedFile($imageId, null, $token));
        self::assertSame($image->getUploadDir().\DIRECTORY_SEPARATOR.$otherFile, $this->servedFile($imageId, $this->otherLocale, $token));
    }

    public function testTheFileOfALanguageCannotBeWrittenThroughTheTranslations(): void
    {
        $product = $this->createProduct();
        $created = $this->upload('/api/admin/product_images', ['product' => (string) $product->getId()], 1, 'default.png');
        $imageId = (int) json_decode((string) $created->getContent(), true)['id'];
        $before = $this->reload(ProductImageQuery::create()->findPk($imageId))->setLocale($this->defaultLocale)->getOwnFile();

        $response = $this->jsonRequest('PATCH', '/api/admin/product_images/'.$imageId, [
            'i18ns' => [$this->defaultLocale => ['title' => 'Renamed', 'file' => '../../config/secret.php']],
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $image = $this->reload(ProductImageQuery::create()->findPk($imageId))->setLocale($this->defaultLocale);
        self::assertSame('Renamed', $image->getTitle());
        self::assertSame($before, $image->getOwnFile());
    }

    private function servedFile(int $imageId, ?string $locale, string $token): string
    {
        $this->client->request(
            'GET',
            '/api/admin/product_images/'.$imageId.'/file'.(null === $locale ? '' : '?locale='.$locale),
            server: ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response, (string) $response->getContent());

        return $response->getFile()->getPathname();
    }

    private function createProduct(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
    }

    private function reload(?ProductImage $image): ProductImage
    {
        self::assertNotNull($image);
        ProductImageTableMap::clearInstancePool();
        ProductImageI18nTableMap::clearInstancePool();

        $reloaded = ProductImageQuery::create()->findPk($image->getId());
        self::assertNotNull($reloaded);

        foreach ($reloaded->getStoredFiles() as $file) {
            $this->trackFileForCleanup($reloaded->getUploadDir().\DIRECTORY_SEPARATOR.$file);
        }

        return $reloaded;
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(string $uri, array $fields, int $width, string $fileName): Response
    {
        $path = tempnam(sys_get_temp_dir(), 'thelia_test_img_');
        $png = imagecreatetruecolor($width, 1);
        imagepng($png, $path);

        $this->client->request(
            'POST',
            $uri,
            parameters: $fields,
            files: ['fileToUpload' => $this->createUploadedFile($path, $fileName, 'image/png')],
            server: [
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->authenticateAsAdmin(),
            ],
        );

        return $this->client->getResponse();
    }
}
