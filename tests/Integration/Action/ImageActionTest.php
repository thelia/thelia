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

namespace Thelia\Tests\Integration\Action;

use Imagine\Gd\Imagine as GdImagine;
use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Image\ImageInterface;
use Imagine\Imagick\Imagine as ImagickImagine;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Image as ImageAction;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileManager;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

final class ImageActionTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;

    private const CACHE_SUBDIRECTORY = 'test-image-action';

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testSaveFilePersistsProductImageAndMovesFile(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $tmpFile = $this->createTestPng();
        $uploadedFile = $this->createUploadedFile($tmpFile, 'test-product.png', 'image/png');

        $model = new ProductImage();
        $model->setProductId($product->getId());
        $model->setVisible(1);
        $model->setPosition(1);

        $event = new FileCreateOrUpdateEvent($product->getId());
        $event
            ->setModel($model)
            ->setUploadedFile($uploadedFile)
            ->setParentName('Test Product');

        $this->dispatch($event, TheliaEvents::IMAGE_SAVE);

        $savedModel = $event->getModel();
        self::assertNotNull($savedModel);
        self::assertGreaterThan(0, $savedModel->getId());
        self::assertNotEmpty($savedModel->getFile());

        $finalPath = $savedModel->getUploadDir().DS.$savedModel->getFile();
        self::assertFileExists($finalPath);
        $this->trackFileForCleanup($finalPath);
    }

    public function testDeleteFileRemovesModelAndFile(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $tmpFile = $this->createTestPng();
        $uploadedFile = $this->createUploadedFile($tmpFile, 'to-delete.png', 'image/png');

        $model = new ProductImage();
        $model->setProductId($product->getId());
        $model->setVisible(1);
        $model->setPosition(1);

        $saveEvent = new FileCreateOrUpdateEvent($product->getId());
        $saveEvent
            ->setModel($model)
            ->setUploadedFile($uploadedFile)
            ->setParentName('Test Product');
        $this->dispatch($saveEvent, TheliaEvents::IMAGE_SAVE);

        $savedModel = $saveEvent->getModel();
        $imageId = $savedModel->getId();
        $filePath = $savedModel->getUploadDir().DS.$savedModel->getFile();
        self::assertFileExists($filePath);

        $this->dispatch(new FileDeleteEvent($savedModel), TheliaEvents::IMAGE_DELETE);

        self::assertNull(ProductImageQuery::create()->findPk($imageId));
        self::assertFileDoesNotExist($filePath);
    }

    public function testToggleVisibilityFlipsImageVisibleFlag(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $tmpFile = $this->createTestPng();
        $uploadedFile = $this->createUploadedFile($tmpFile, 'toggle-vis.png', 'image/png');

        $model = new ProductImage();
        $model->setProductId($product->getId());
        $model->setVisible(1);
        $model->setPosition(1);

        $saveEvent = new FileCreateOrUpdateEvent($product->getId());
        $saveEvent
            ->setModel($model)
            ->setUploadedFile($uploadedFile)
            ->setParentName('Test Product');
        $this->dispatch($saveEvent, TheliaEvents::IMAGE_SAVE);

        $savedModel = $saveEvent->getModel();
        $this->trackFileForCleanup($savedModel->getUploadDir().DS.$savedModel->getFile());

        self::assertSame(1, (int) $savedModel->getVisible());

        $toggleEvent = new FileToggleVisibilityEvent(
            ProductImageQuery::create(),
            $savedModel->getId(),
        );
        $this->dispatch($toggleEvent, TheliaEvents::IMAGE_TOGGLE_VISIBILITY);

        $reloaded = ProductImageQuery::create()->findPk($savedModel->getId());
        self::assertSame(0, (int) $reloaded->getVisible());
    }

    public function testProcessImageServesAnSvgWithoutRasterizingIt(): void
    {
        $sourceFile = $this->createTestSvg();
        $this->trackFileForCleanup($sourceFile);

        $event = (new ImageEvent())
            ->setSourceFilepath($sourceFile)
            ->setCacheSubdirectory(self::CACHE_SUBDIRECTORY);

        $this->dispatch($event, TheliaEvents::IMAGE_PROCESS);

        $cacheFilePath = $event->getCacheFilepath();
        self::assertNotNull($cacheFilePath);
        $this->trackFileForCleanup($cacheFilePath);
        $this->trackFileForCleanup($event->getCacheOriginalFilepath());

        self::assertFileExists($cacheFilePath);
        self::assertNotEmpty($event->getFileUrl());
        self::assertNull($event->getImageObject(), 'A vector image must never be handed to the raster pipeline.');
    }

    public function testProcessImageRescalesAnSvgWithoutRasterizingIt(): void
    {
        $sourceFile = $this->createTestSvg();
        $this->trackFileForCleanup($sourceFile);

        $event = (new ImageEvent())
            ->setSourceFilepath($sourceFile)
            ->setCacheSubdirectory(self::CACHE_SUBDIRECTORY)
            ->setWidth(64);

        $this->dispatch($event, TheliaEvents::IMAGE_PROCESS);

        $cacheFilePath = $event->getCacheFilepath();
        self::assertNotNull($cacheFilePath);
        $this->trackFileForCleanup($cacheFilePath);
        $this->trackFileForCleanup($event->getCacheOriginalFilepath());

        self::assertStringContainsString('width="64"', (string) file_get_contents($cacheFilePath));
        self::assertNull($event->getImageObject(), 'A vector image must never be handed to the raster pipeline.');
    }

    public function testProcessImageStillAttachesAnImageObjectForRasterImages(): void
    {
        $sourceFile = $this->createTestPng();
        $this->trackFileForCleanup($sourceFile);

        $event = (new ImageEvent())
            ->setSourceFilepath($sourceFile)
            ->setCacheSubdirectory(self::CACHE_SUBDIRECTORY);

        $this->dispatch($event, TheliaEvents::IMAGE_PROCESS);

        $this->trackFileForCleanup((string) $event->getCacheFilepath());
        $this->trackFileForCleanup($event->getCacheOriginalFilepath());

        self::assertNotNull($event->getImageObject());
    }

    /**
     * Uploading a file and transforming an API resource both process an image
     * and read only its url or its path afterwards: neither must decode it.
     */
    public function testProcessImageDoesNotDecodeTheCacheFileWithoutADemand(): void
    {
        $sourceFile = $this->createTestPng();
        $this->trackFileForCleanup($sourceFile);

        $countingImagine = new class extends GdImagine {
            public int $opens = 0;

            public function open($path): ImageInterface
            {
                ++$this->opens;

                return parent::open($path);
            }
        };

        $action = new class(new FileManager([]), $countingImagine) extends ImageAction {
            public function __construct(FileManager $fileManager, private readonly GdImagine $countingImagine)
            {
                parent::__construct($fileManager);
            }

            protected function createImagineInstance(): ImagickImagine|GmagickImagine|GdImagine
            {
                return $this->countingImagine;
            }
        };

        $event = (new ImageEvent())
            ->setSourceFilepath($sourceFile)
            ->setCacheSubdirectory(self::CACHE_SUBDIRECTORY);

        $action->processImage($event, TheliaEvents::IMAGE_PROCESS, new EventDispatcher());

        $this->trackFileForCleanup((string) $event->getCacheFilepath());
        $this->trackFileForCleanup($event->getCacheOriginalFilepath());

        self::assertNotEmpty($event->getFileUrl(), 'sanity: the url must be there without ever decoding the image.');
        self::assertSame(0, $countingImagine->opens, 'The cache file must not be decoded until something asks for the image object.');

        self::assertNotNull($event->getImageObject());
        self::assertSame(1, $countingImagine->opens, 'Asking for the image object must decode it.');

        $event->getImageObject();
        self::assertSame(1, $countingImagine->opens, 'A second demand must reuse the decoded image, not decode it again.');
    }
}
