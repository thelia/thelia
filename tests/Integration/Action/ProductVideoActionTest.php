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

use Thelia\Action\ProductVideo as ProductVideoAction;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Domain\Media\DTO\ProductVideoCreateDTO;
use Thelia\Domain\Media\DTO\ProductVideoUpdateDTO;
use Thelia\Domain\Media\MediaFacade;
use Thelia\Domain\Media\Video\VideoProvider;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsProductVideo;
use Thelia\Model\ProductSaleElementsProductVideoQuery;
use Thelia\Model\ProductVideo;
use Thelia\Model\ProductVideoQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The product video, driven the way the back office and the API drive it: through
 * the media facade and the events behind it.
 */
final class ProductVideoActionTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;

    private MediaFacade $mediaFacade;

    private ProductVideoAction $videoAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaFacade = $this->getService(MediaFacade::class);
        $this->videoAction = $this->getService(ProductVideoAction::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testAPlatformVideoStoresTheIdentifierAndNoFile(): void
    {
        $product = $this->createProduct();

        $video = $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
            productId: $product->getId(),
            provider: VideoProvider::Youtube,
            externalId: 'dQw4w9WgXcQ',
            locale: 'en_US',
            title: 'Demo',
            alt: 'A demonstration of the bag',
        ));

        self::assertGreaterThan(0, $video->getId());
        self::assertSame('youtube', $video->getProvider());
        self::assertSame('dQw4w9WgXcQ', $video->getExternalId());
        self::assertSame('', $video->getFile());
        self::assertSame(1, $video->getVisible());
        self::assertSame('A demonstration of the bag', $video->setLocale('en_US')->getAlt());
    }

    public function testAHostedVideoIsCopiedIntoTheVideoLibrary(): void
    {
        $product = $this->createProduct();

        $video = $this->createHostedVideo($product);

        self::assertSame('file', $video->getProvider());
        self::assertNotSame('', $video->getFile());
        self::assertStringContainsString(
            'local'.DS.'media'.DS.'videos'.DS.'product',
            $video->getUploadDir(),
        );

        $path = $video->getUploadDir().DS.$video->getFile();
        $this->trackFileForCleanup($path);
        self::assertFileExists($path);
    }

    public function testAHostedVideoIsPublishedInTheWebSpace(): void
    {
        $product = $this->createProduct();
        $video = $this->createHostedVideo($product);

        $cachedFile = $this->videoAction->cachedFilePath($video);
        $this->trackFileForCleanup($cachedFile);
        self::assertFileDoesNotExist($cachedFile, 'Nothing is published until the address is asked for.');

        $url = $this->publish($video);

        self::assertTrue(file_exists($cachedFile) || is_link($cachedFile));
        self::assertStringContainsString('/cache/videos/product/', $url);
        self::assertStringNotContainsString('/local/', $url, 'The video library is outside the web space.');
    }

    public function testDeletingAVideoUnpublishesIt(): void
    {
        $product = $this->createProduct();
        $video = $this->createHostedVideo($product);
        $this->publish($video);

        $cachedFile = $this->videoAction->cachedFilePath($video);
        $this->trackFileForCleanup($cachedFile);
        self::assertTrue(file_exists($cachedFile) || is_link($cachedFile));

        $this->mediaFacade->deleteVideo($video);

        self::assertFalse(
            file_exists($cachedFile) || is_link($cachedFile),
            'The link that published the video must go with it.',
        );
    }

    public function testWordingIsWrittenPerLanguage(): void
    {
        $product = $this->createProduct();
        $video = $this->platformVideo($product, ['title' => 'Demo']);

        $this->mediaFacade->updateVideo($video, new ProductVideoUpdateDTO(
            locale: 'fr_FR',
            title: 'Démo',
            alt: 'Vidéo de démonstration du sac',
        ));

        $reloaded = ProductVideoQuery::create()->findPk($video->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Démo', $reloaded->setLocale('fr_FR')->getTitle());
        self::assertSame('Vidéo de démonstration du sac', $reloaded->setLocale('fr_FR')->getAlt());
        self::assertSame('Demo', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testPositionsAreCountedWithinTheProduct(): void
    {
        $firstProduct = $this->createProduct();
        $secondProduct = $this->createProduct();

        $first = $this->platformVideo($firstProduct);
        $second = $this->platformVideo($firstProduct);
        $otherProductVideo = $this->platformVideo($secondProduct);

        self::assertSame(1, $first->getPosition());
        self::assertSame(2, $second->getPosition());
        self::assertSame(1, $otherProductVideo->getPosition(), 'Positions restart on each product.');

        $this->mediaFacade->updateVideoPosition($second, 1, UpdatePositionEvent::POSITION_ABSOLUTE);

        self::assertSame(1, ProductVideoQuery::create()->findPk($second->getId())?->getPosition());
        self::assertSame(2, ProductVideoQuery::create()->findPk($first->getId())?->getPosition());
        self::assertSame(
            1,
            ProductVideoQuery::create()->findPk($otherProductVideo->getId())?->getPosition(),
            'Reordering one product must leave the videos of another alone.',
        );
    }

    public function testVisibilityIsFlipped(): void
    {
        $video = $this->platformVideo($this->createProduct());
        self::assertSame(1, $video->getVisible());

        $this->mediaFacade->toggleVideoVisibility($video);
        self::assertSame(0, ProductVideoQuery::create()->findPk($video->getId())?->getVisible());

        $this->mediaFacade->toggleVideoVisibility($video);
        self::assertSame(1, ProductVideoQuery::create()->findPk($video->getId())?->getVisible());
    }

    public function testDeletingAHostedVideoTakesItsFileWithIt(): void
    {
        $product = $this->createProduct();
        $video = $this->createHostedVideo($product);
        $path = $video->getUploadDir().DS.$video->getFile();
        self::assertFileExists($path);

        $this->mediaFacade->deleteVideo($video);

        self::assertNull(ProductVideoQuery::create()->findPk($video->getId()));
        self::assertFileDoesNotExist($path);
    }

    public function testDeletingAProductTakesItsVideosAndTheirFiles(): void
    {
        $product = $this->createProduct();
        $platformVideo = $this->platformVideo($product);
        $hostedVideo = $this->createHostedVideo($product);
        $path = $hostedVideo->getUploadDir().DS.$hostedVideo->getFile();
        self::assertFileExists($path);

        $this->dispatch(new ProductDeleteEvent($product->getId()), TheliaEvents::PRODUCT_DELETE);

        self::assertNull(ProductVideoQuery::create()->findPk($platformVideo->getId()));
        self::assertNull(ProductVideoQuery::create()->findPk($hostedVideo->getId()));
        self::assertFileDoesNotExist($path);
    }

    public function testAVideoIsAttachedToACombination(): void
    {
        $product = $this->createProduct();
        $video = $this->platformVideo($product);
        $combination = $this->factory->productSaleElement($product);

        $link = new ProductSaleElementsProductVideo();
        $link
            ->setProductSaleElementsId($combination->getId())
            ->setProductVideoId($video->getId())
            ->save();

        $found = ProductSaleElementsProductVideoQuery::create()
            ->filterByProductVideoId($video->getId())
            ->findOne();

        self::assertNotNull($found);
        self::assertSame($combination->getId(), $found->getProductSaleElementsId());
        self::assertCount(1, $video->getProductSaleElementsProductVideos());
    }

    /**
     * The smallest file a mime type guesser reads as an MP4: an ftyp box and
     * nothing after it. The shop checks the type it guesses, not the one the
     * client declares.
     */
    private function createTestMp4(): string
    {
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('thelia_test_video_').'.mp4';
        file_put_contents(
            $path,
            "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41\x00\x00\x00\x08free".str_repeat("\x00", 64),
        );
        $this->trackFileForCleanup($path);

        return $path;
    }

    /**
     * Publishes a hosted video in the web space the way the API read does, and
     * hands back the address it is served from.
     */
    private function publish(ProductVideo $video): string
    {
        $event = new DocumentEvent();
        $event->setSourceFilepath($video->getUploadDir().DS.$video->getFile());
        $event->setCacheSubdirectory(ProductVideoAction::CACHE_SUBDIRECTORY);

        $this->dispatch($event, TheliaEvents::PRODUCT_VIDEO_PROCESS);

        return (string) $event->getDocumentUrl();
    }

    private function createProduct(): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );
    }

    private function platformVideo(Product $product, array $overrides = []): ProductVideo
    {
        return $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
            productId: $product->getId(),
            provider: VideoProvider::Youtube,
            externalId: $overrides['externalId'] ?? 'dQw4w9WgXcQ',
            locale: 'en_US',
            title: $overrides['title'] ?? null,
        ));
    }

    private function createHostedVideo(Product $product): ProductVideo
    {
        $uploadedFile = $this->createUploadedFile($this->createTestMp4(), 'demo.mp4', 'video/mp4');

        $video = $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
            productId: $product->getId(),
            provider: VideoProvider::File,
            uploadedFile: $uploadedFile,
            locale: 'en_US',
            title: 'Assembly',
        ));

        $this->trackFileForCleanup($video->getUploadDir().DS.$video->getFile());

        return $video;
    }
}
