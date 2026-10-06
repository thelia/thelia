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

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Model\Binary;
use Thelia\Action\Image;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * Deleting an image or a document takes with it the copies the shop published to serve
 * it: the link or copy in the cache directory, the resized versions, the filter variants.
 */
final class DeletedMediaCopiesTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;

    private const string FILTER = 'thelia_test_deleted_media';

    private CacheManager $imagineCacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FilterConfiguration $filters */
        $filters = $this->getService('liip_imagine.filter.configuration');
        $filters->set(self::FILTER, []);

        /** @var CacheManager $cacheManager */
        $cacheManager = $this->getService(CacheManager::class);
        $this->imagineCacheManager = $cacheManager;
    }

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();

        $filterDirectory = THELIA_WEB_DIR.'media/cache/'.self::FILTER;
        @rmdir($filterDirectory.'/product');
        @rmdir($filterDirectory);

        parent::tearDown();
    }

    public function testDeletingAnImageRemovesEveryPublishedCopy(): void
    {
        $image = $this->createImage(Lang::getDefaultLanguage()->getLocale(), 'deleted.png');
        $file = (string) $image->getOwnFile();

        $original = $this->processImage($image, $file, null);
        $resized = $this->processImage($image, $file, 40);
        $filterPath = 'product/'.$file;
        $this->imagineCacheManager->store(new Binary('png', 'image/png', 'png'), $filterPath, self::FILTER);
        $this->imagineCacheManager->store(new Binary('webp', 'image/webp', 'webp'), $filterPath.'.webp', self::FILTER);

        self::assertTrue(is_link($original));
        self::assertFileExists($resized);
        self::assertTrue($this->imagineCacheManager->isStored($filterPath, self::FILTER));

        $this->dispatch(new FileDeleteEvent($image), TheliaEvents::IMAGE_DELETE);

        self::assertFalse(is_link($original) || file_exists($original), 'The link to the original stays published.');
        self::assertFileDoesNotExist($resized);
        self::assertFalse($this->imagineCacheManager->isStored($filterPath, self::FILTER));
        self::assertFalse($this->imagineCacheManager->isStored($filterPath.'.webp', self::FILTER));
    }

    public function testDeletingAnImageRemovesTheCopiesOfEveryLanguage(): void
    {
        $otherLocale = (string) LangQuery::create()->filterByByDefault(0)->orderByLocale()->findOne()?->getLocale();
        self::assertNotSame('', $otherLocale, 'The test database has a single language.');

        $image = $this->createImage(Lang::getDefaultLanguage()->getLocale(), 'default.png');
        $this->addFileForLocale($image, $otherLocale, 'other.png');

        $copies = [];

        foreach ($image->getStoredFiles() as $file) {
            $copies[] = $this->processImage($image, $file, 40);
        }

        self::assertCount(2, $copies);

        $this->dispatch(new FileDeleteEvent($image), TheliaEvents::IMAGE_DELETE);

        foreach ($copies as $copy) {
            self::assertFileDoesNotExist($copy);
        }
    }

    public function testDeletingAnImageKeepsTheCopiesOfTheOtherImages(): void
    {
        $locale = Lang::getDefaultLanguage()->getLocale();
        $deleted = $this->createImage($locale, 'gone.png');
        $kept = $this->createImage($locale, 'kept.png');

        $this->processImage($deleted, (string) $deleted->getOwnFile(), 40);
        $keptCopy = $this->processImage($kept, (string) $kept->getOwnFile(), 40);
        $this->trackFileForCleanup($keptCopy);

        $this->dispatch(new FileDeleteEvent($deleted), TheliaEvents::IMAGE_DELETE);

        self::assertFileExists($keptCopy);
    }

    public function testDeletingADocumentRemovesItsPublishedLink(): void
    {
        $document = new ProductDocument();
        $document->setProductId($this->createProduct()->getId())->setVisible(1)->setLocale(Lang::getDefaultLanguage()->getLocale())->setTitle('Document');

        $event = new FileCreateOrUpdateEvent($document->getProductId());
        $event
            ->setModel($document)
            ->setUploadedFile($this->createUploadedFile($this->createTestTextFile('%PDF-1.4'), 'deleted.pdf', 'application/pdf'))
            ->setParentName('Product');
        $this->dispatch($event, TheliaEvents::DOCUMENT_SAVE);
        $this->trackFileForCleanup($document->getUploadDir().DS.$document->getFile());

        $process = new DocumentEvent();
        $process
            ->setSourceFilepath($document->getUploadDir().DS.$document->getFile())
            ->setCacheSubdirectory('product');
        $this->dispatch($process, TheliaEvents::DOCUMENT_PROCESS);

        $published = rtrim(THELIA_WEB_DIR, '/').'/cache/documents/product/'.strtolower($document->getFile());
        self::assertTrue(is_link($published) || file_exists($published));

        $this->dispatch(new FileDeleteEvent($document), TheliaEvents::DOCUMENT_DELETE);

        self::assertFalse(is_link($published) || file_exists($published), 'The published document stays served.');
    }

    private function createImage(string $locale, string $name): ProductImage
    {
        $model = new ProductImage();
        $model->setProductId($this->createProduct()->getId())->setVisible(1)->setLocale($locale)->setTitle('Image');

        $event = new FileCreateOrUpdateEvent($model->getProductId());
        $event
            ->setModel($model)
            ->setUploadedFile($this->createUploadedFile($this->createTestPng(), $name, 'image/png'))
            ->setParentName('Product');

        $this->dispatch($event, TheliaEvents::IMAGE_SAVE);

        $this->trackFileForCleanup($model->getUploadDir().DS.$model->getOwnFile());

        return $model;
    }

    private function addFileForLocale(ProductImage $image, string $locale, string $name): void
    {
        $oldModel = clone $image;
        $image->setLocale($locale);

        $event = new FileCreateOrUpdateEvent($image->getProductId());
        $event
            ->setModel($image)
            ->setUploadedFile($this->createUploadedFile($this->createTestPng(), $name, 'image/png'));
        $event->setOldModel($oldModel);

        $this->dispatch($event, TheliaEvents::IMAGE_UPDATE);

        $this->trackFileForCleanup($image->getUploadDir().DS.$image->getOwnFile());
    }

    /**
     * Publishes the file of an image, as the original when no width is given, and
     * returns the path of the published copy.
     */
    private function processImage(ProductImage $image, string $file, ?int $width): string
    {
        $event = new ImageEvent();
        $event
            ->setSourceFilepath($image->getUploadDir().DS.$file)
            ->setCacheSubdirectory('product');

        if (null !== $width) {
            $event->setWidth($width)->setHeight($width)->setResizeMode((string) Image::KEEP_IMAGE_RATIO);
        }

        $this->dispatch($event, TheliaEvents::IMAGE_PROCESS);

        return (string) $event->getCacheFilepath();
    }

    private function createProduct(): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );
    }
}
