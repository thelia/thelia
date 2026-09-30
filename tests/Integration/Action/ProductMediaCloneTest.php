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

use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Media\Video\VideoProvider;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Model\Map\ProductVideoTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductSaleElementsProductVideo;
use Thelia\Model\ProductSaleElementsProductVideoQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\ProductVideo;
use Thelia\Model\ProductVideoQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * A clone publishes the same gallery as its source: the images with what they
 * say to a visitor who does not see them, and the videos in the same sequence,
 * with their thumbnail and their combinations.
 */
final class ProductMediaCloneTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testTheClonedImageKeepsItsAltTextAndItsDecorativeFlag(): void
    {
        $source = $this->sourceProduct();
        $image = $this->factory->productImage($source, [
            'locale' => 'en_US',
            'title' => 'Front view',
            'alt' => 'The bag seen from the front',
            'decorative' => true,
        ]);
        $this->trackFileForCleanup($image->getUploadDir().DS.$image->getFile());

        $clonedProduct = $this->cloneOf($source);

        ProductImageTableMap::clearInstancePool();
        $clone = ProductImageQuery::create()->findOneByProductId($clonedProduct->getId());
        self::assertNotNull($clone, 'The image was cloned.');
        $this->trackFileForCleanup($clone->getUploadDir().DS.$clone->setLocale('en_US')->getFile());

        self::assertSame(1, $clone->getDecorative(), 'The clone of a decorative image is decorative.');
        self::assertSame('The bag seen from the front', $clone->setLocale('en_US')->getAlt());
        self::assertSame('Front view', $clone->setLocale('en_US')->getTitle(), 'The clone keeps the wording of each language of the source.');
    }

    public function testTheClonedProductKeepsItsPlatformVideoWithItsThumbnailAndItsCombination(): void
    {
        $source = $this->sourceProduct();
        $image = $this->factory->productImage($source);
        $this->trackFileForCleanup($image->getUploadDir().DS.$image->getFile());
        $video = $this->factory->productVideo($source, [
            'externalId' => 'dQw4w9WgXcQ',
            'locale' => 'en_US',
            'title' => 'Unboxing',
            'alt' => 'The bag taken out of its box',
            'thumbnailImageId' => $image->getId(),
            'visible' => 0,
        ]);
        $lastImage = $this->factory->productImage($source);
        $this->trackFileForCleanup($lastImage->getUploadDir().DS.$lastImage->getFile());
        $combination = ProductSaleElementsQuery::create()->findOneByProductId($source->getId());
        (new ProductSaleElementsProductVideo())
            ->setProductSaleElementsId($combination->getId())
            ->setProductVideoId($video->getId())
            ->save();

        $clonedProduct = $this->cloneOf($source);

        ProductVideoTableMap::clearInstancePool();
        ProductImageTableMap::clearInstancePool();
        $clonedVideos = ProductVideoQuery::create()->filterByProductId($clonedProduct->getId())->find();
        self::assertCount(1, $clonedVideos, 'The clone carries the video of the source product.');

        /** @var ProductVideo $clonedVideo */
        $clonedVideo = $clonedVideos->getFirst();
        $clonedImages = ProductImageQuery::create()->filterByProductId($clonedProduct->getId())->orderById()->find();
        self::assertCount(2, $clonedImages);
        foreach ($clonedImages as $clonedImage) {
            $this->trackFileForCleanup($clonedImage->getUploadDir().DS.$clonedImage->setLocale('en_US')->getFile());
        }
        [$clonedImage, $clonedLastImage] = $clonedImages->getData();

        self::assertSame(VideoProvider::Youtube->value, $clonedVideo->getProvider());
        self::assertSame('dQw4w9WgXcQ', $clonedVideo->getExternalId());
        self::assertSame(0, $clonedVideo->getVisible());
        self::assertSame(
            [1, 2, 3],
            [$clonedImage->getPosition(), $clonedVideo->getPosition(), $clonedLastImage->getPosition()],
            'The clone shows its images and its video in the order of the source gallery.',
        );
        self::assertSame('Unboxing', $clonedVideo->setLocale('en_US')->getTitle());
        self::assertSame('The bag taken out of its box', $clonedVideo->setLocale('en_US')->getAlt());
        self::assertSame($clonedImage->getId(), $clonedVideo->getThumbnailImageId(), 'The thumbnail is the copy of the image, not the image of the source.');

        $clonedCombinationIds = ProductSaleElementsQuery::create()->filterByProductId($clonedProduct->getId())->select(['Id'])->find()->getData();
        $links = ProductSaleElementsProductVideoQuery::create()->filterByProductVideoId($clonedVideo->getId())->find();
        self::assertCount(1, $links, 'The cloned video is bound to the cloned combination.');
        self::assertContains($links->getFirst()->getProductSaleElementsId(), $clonedCombinationIds);
    }

    public function testTheClonedProductHasItsOwnCopyOfAHostedVideo(): void
    {
        $source = $this->sourceProduct();
        $video = $this->factory->productVideo($source, [
            'provider' => VideoProvider::File->value,
            'externalId' => null,
            'file' => 'hosted-'.$source->getRef().'.mp4',
        ]);
        $sourceFile = $video->getUploadDir().DS.$video->getFile();
        @mkdir(\dirname($sourceFile), 0o777, true);
        file_put_contents($sourceFile, "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41");
        $this->trackFileForCleanup($sourceFile);

        $clonedProduct = $this->cloneOf($source);

        ProductVideoTableMap::clearInstancePool();
        $clonedVideo = ProductVideoQuery::create()->findOneByProductId($clonedProduct->getId());
        self::assertNotNull($clonedVideo, 'The hosted video was cloned.');
        $clonedFile = $clonedVideo->getUploadDir().DS.$clonedVideo->getFile();
        $this->trackFileForCleanup($clonedFile);

        self::assertSame(VideoProvider::File->value, $clonedVideo->getProvider());
        self::assertNotSame($video->getFile(), $clonedVideo->getFile(), 'Deleting one product must not take the file of the other.');
        self::assertFileExists($clonedFile);
        self::assertFileExists($sourceFile, 'The source keeps its file.');
    }

    private function sourceProduct(): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        // cloneProduct() reads the source i18n row, which the fixture does not create.
        $product->setLocale('en_US')->setTitle('Product with media')->save();

        return $product;
    }

    private function cloneOf(Product $source): Product
    {
        $event = new ProductCloneEvent($source->getRef().'-CLONE', 'en_US', $source);
        $this->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        $clonedProduct = $event->getClonedProduct();
        self::assertNotNull($clonedProduct, 'The product was cloned.');

        return $clonedProduct;
    }
}
