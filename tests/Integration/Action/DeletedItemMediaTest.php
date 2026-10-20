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

use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileModelInterface;
use Thelia\Model\BrandImage;
use Thelia\Model\CategoryImage;
use Thelia\Model\Lang;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * Deleting an item deletes the files of its images and documents, and the copies
 * published to serve them. Their rows go with the item through the foreign keys,
 * and an image only knows its file through its translations.
 */
final class DeletedItemMediaTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();

        parent::tearDown();
    }

    public function testDeletingAProductDeletesTheFilesOfItsImagesAndDocuments(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        $image = $this->attach(new ProductImage(), $product->getId(), 'Product', 'deleted-product.png', $this->createTestPng(), 'image/png');
        $imageFile = $image->getUploadDir().DS.$image->getOwnFile();
        $publishedCopy = $this->publish($imageFile, 'product');

        $document = $this->attach(new ProductDocument(), $product->getId(), 'Product', 'deleted-product.pdf', $this->createTestTextFile('%PDF-1.4'), 'application/pdf');
        $documentFile = $document->getUploadDir().DS.$document->getFile();

        self::assertFileExists($imageFile);
        self::assertFileExists($documentFile);
        self::assertTrue(is_link($publishedCopy) || is_file($publishedCopy));

        $this->dispatch(new ProductDeleteEvent($product->getId()), TheliaEvents::PRODUCT_DELETE);

        self::assertFileDoesNotExist($imageFile);
        self::assertFileDoesNotExist($documentFile);
        self::assertFalse(is_link($publishedCopy) || file_exists($publishedCopy), 'The published copy stays served.');
    }

    public function testDeletingACategoryDeletesTheFilesOfItsImages(): void
    {
        $category = $this->factory->category();

        $image = $this->attach(new CategoryImage(), $category->getId(), 'Category', 'deleted-category.png', $this->createTestPng(), 'image/png');
        $imageFile = $image->getUploadDir().DS.$image->getOwnFile();

        $this->dispatch(new CategoryDeleteEvent($category->getId()), TheliaEvents::CATEGORY_DELETE);

        self::assertFileDoesNotExist($imageFile);
    }

    public function testDeletingABrandDeletesTheFilesOfItsImages(): void
    {
        $brand = $this->factory->brand();

        $image = $this->attach(new BrandImage(), $brand->getId(), 'Brand', 'deleted-brand.png', $this->createTestPng(), 'image/png');
        $imageFile = $image->getUploadDir().DS.$image->getOwnFile();

        $this->dispatch(new BrandDeleteEvent($brand->getId()), TheliaEvents::BRAND_DELETE);

        self::assertFileDoesNotExist($imageFile);
    }

    /**
     * @template T of FileModelInterface
     *
     * @param T $model
     *
     * @return T
     */
    private function attach(FileModelInterface $model, int $parentId, string $parentName, string $name, string $source, string $mimeType): FileModelInterface
    {
        $model->setParentId($parentId);
        $model->setVisible(1)->setLocale(Lang::getDefaultLanguage()->getLocale())->setTitle($name);

        $event = new FileCreateOrUpdateEvent($parentId);
        $event
            ->setModel($model)
            ->setUploadedFile($this->createUploadedFile($source, $name, $mimeType))
            ->setParentName($parentName);

        $this->dispatch($event, $model instanceof ProductDocument ? TheliaEvents::DOCUMENT_SAVE : TheliaEvents::IMAGE_SAVE);

        // Removed by the test when the code under test leaves it behind.
        $this->trackFileForCleanup($model->getUploadDir().DS.$model->getFile());

        return $model;
    }

    private function publish(string $storedFile, string $subdirectory): string
    {
        $event = new ImageEvent();
        $event
            ->setSourceFilepath($storedFile)
            ->setCacheSubdirectory($subdirectory);

        $this->dispatch($event, TheliaEvents::IMAGE_PROCESS);

        $published = (string) $event->getCacheFilepath();
        $this->trackFileForCleanup($published);

        return $published;
    }
}
