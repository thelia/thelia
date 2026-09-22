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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Action\ProductVideo as ProductVideoAction;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\File\Exception\ProcessFileException;
use Thelia\Domain\Media\DTO\ProductVideoCreateDTO;
use Thelia\Domain\Media\DTO\ProductVideoUpdateDTO;
use Thelia\Domain\Media\MediaFacade;
use Thelia\Domain\Media\Video\IncompleteVideoException;
use Thelia\Domain\Media\Video\VideoProvider;
use Thelia\Model\Product;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
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

    /**
     * The video library is published into the web space by symbolic link, so a file
     * the shop accepts is a file the shop serves: a name the web server would
     * execute has to be refused before anything is written, whoever is uploading —
     * the API, a back-office screen, or a module calling the facade.
     */
    public function testAnExecutableNameIsRefusedWhoeverUploadsIt(): void
    {
        $product = $this->createProduct();

        foreach (['shell.php', 'shell.php.mp4'] as $fileName) {
            $before = $this->videoLibraryContents();

            try {
                $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
                    productId: $product->getId(),
                    provider: VideoProvider::File,
                    uploadedFile: $this->createUploadedFile(
                        $this->createTestTextFile('<?php echo 1;', 'thelia_test_video_'),
                        $fileName,
                        'video/mp4',
                    ),
                ));
                self::fail(\sprintf('"%s" must not be accepted as a video.', $fileName));
            } catch (ProcessFileException $exception) {
                self::assertSame(415, $exception->getCode());
            }

            self::assertSame($before, $this->videoLibraryContents(), 'Nothing may be written for a refused upload.');
        }

        self::assertNull(ProductVideoQuery::create()->filterByProductId($product->getId())->findOne());
    }

    public function testAFileThatIsNotAVideoIsRefused(): void
    {
        $product = $this->createProduct();

        $this->expectException(ProcessFileException::class);

        $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
            productId: $product->getId(),
            provider: VideoProvider::File,
            uploadedFile: $this->createUploadedFile(
                $this->createTestTextFile('not a video', 'thelia_test_video_'),
                'assembly.mp4',
                'video/mp4',
            ),
        ));
    }

    /**
     * A row pointing at neither a file nor a platform identifier can never be
     * played, and nothing downstream would report it: it would simply be one item
     * missing from a gallery.
     */
    public function testAVideoThatPointsAtNothingIsRefused(): void
    {
        $product = $this->createProduct();

        try {
            $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
                productId: $product->getId(),
                provider: VideoProvider::File,
            ));
            self::fail('A hosted video with no file must be refused.');
        } catch (IncompleteVideoException $exception) {
            self::assertStringContainsString('needs a file', $exception->getMessage());
        }

        try {
            $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
                productId: $product->getId(),
                provider: VideoProvider::Youtube,
            ));
            self::fail('A platform video with no identifier must be refused.');
        } catch (IncompleteVideoException $exception) {
            self::assertStringContainsString('needs the identifier', $exception->getMessage());
        }

        self::assertNull(ProductVideoQuery::create()->filterByProductId($product->getId())->findOne());
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

    /**
     * @return list<string>
     */
    private function videoLibraryContents(): array
    {
        $directory = (new ProductVideo())->getUploadDir();

        if (!is_dir($directory)) {
            return [];
        }

        return array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    }

    public function testAFileTheServerRefusedForItsSizeIsNotStored(): void
    {
        $product = $this->createProduct();

        // What PHP hands the application when the upload went past upload_max_filesize:
        // the file is there, its error says why it is not usable.
        $tooLarge = new UploadedFile($this->createTestMp4(), 'demo.mp4', 'video/mp4', \UPLOAD_ERR_INI_SIZE, true);

        try {
            $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
                productId: $product->getId(),
                provider: VideoProvider::File,
                uploadedFile: $tooLarge,
                locale: 'en_US',
                title: 'Too large',
            ));
            self::fail('A file the server refused for its size must not be stored.');
        } catch (ProcessFileException $exception) {
            self::assertStringContainsString('too large', strtolower($exception->getMessage()));
        }

        self::assertSame(0, ProductVideoQuery::create()->filterByProductId($product->getId())->count());
    }

    public function testReplacingTheAddressOfAPlatformVideoKeepsEverythingElse(): void
    {
        $product = $this->createProduct();
        $image = $this->productImage($product);
        $video = $this->platformVideo($product);
        $this->mediaFacade->updateVideo($video, new ProductVideoUpdateDTO(
            locale: 'en_US',
            thumbnailImageId: $image->getId(),
            title: 'Demo',
            alt: 'A demonstration of the bag',
        ));
        $position = $video->getPosition();

        $this->mediaFacade->updateVideo($video, new ProductVideoUpdateDTO(
            provider: VideoProvider::Vimeo,
            externalId: '76979871',
            locale: 'en_US',
        ));

        $reloaded = ProductVideoQuery::create()->findPk($video->getId());
        self::assertNotNull($reloaded);
        self::assertSame('vimeo', $reloaded->getProvider());
        self::assertSame('76979871', $reloaded->getExternalId());
        // What the merchant arranged around the video is not his to type again.
        self::assertSame($position, $reloaded->getPosition());
        self::assertSame($image->getId(), $reloaded->getThumbnailImageId());
        self::assertSame('Demo', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('A demonstration of the bag', $reloaded->setLocale('en_US')->getAlt());
    }

    public function testLeavingAHostedFileForAPlatformTakesTheFileWithIt(): void
    {
        $product = $this->createProduct();
        $video = $this->createHostedVideo($product);

        $stored = $video->getUploadDir().DS.$video->getFile();
        $published = $this->videoAction->cachedFilePath($video);
        self::assertFileExists($stored);

        $this->mediaFacade->updateVideo($video, new ProductVideoUpdateDTO(
            provider: VideoProvider::Youtube,
            externalId: 'dQw4w9WgXcQ',
            locale: 'en_US',
        ));

        $reloaded = ProductVideoQuery::create()->findPk($video->getId());
        self::assertNotNull($reloaded);
        self::assertSame('youtube', $reloaded->getProvider());
        self::assertSame('', $reloaded->getFile(), 'The row no longer names a file.');
        self::assertFileDoesNotExist($stored, 'The file the shop was serving goes with the source that used it.');
        self::assertFileDoesNotExist($published, 'And so does its copy in the web space.');
    }

    public function testReplacingAHostedFileRemovesTheOneItHeld(): void
    {
        $product = $this->createProduct();
        $video = $this->createHostedVideo($product);
        $firstStored = $video->getUploadDir().DS.$video->getFile();

        $this->mediaFacade->updateVideo($video, new ProductVideoUpdateDTO(
            uploadedFile: $this->createUploadedFile($this->createTestMp4(), 'other.mp4', 'video/mp4'),
            locale: 'en_US',
        ));

        $reloaded = ProductVideoQuery::create()->findPk($video->getId());
        self::assertNotNull($reloaded);
        self::assertSame('file', $reloaded->getProvider());
        self::assertNotSame('', $reloaded->getFile());
        $this->trackFileForCleanup($reloaded->getUploadDir().DS.$reloaded->getFile());
        $this->trackFileForCleanup($this->videoAction->cachedFilePath($reloaded));
        self::assertFileDoesNotExist($firstStored, 'The file it held is not left behind.');
    }

    public function testANewMediumGoesAfterEveryImageAndVideoOfTheProduct(): void
    {
        $product = $this->createProduct();

        $firstImage = $this->productImage($product);
        $video = $this->platformVideo($product);
        $secondImage = $this->productImage($product);
        $secondVideo = $this->platformVideo($product);

        self::assertSame(1, $firstImage->getPosition());
        self::assertSame(2, $video->getPosition(), 'A video goes after the images the product already has.');
        self::assertSame(3, $secondImage->getPosition(), 'An image goes after the videos the product already has.');
        self::assertSame(4, $secondVideo->getPosition());
    }

    public function testTheMediaOfAProductAreReorderedAsOneList(): void
    {
        $product = $this->createProduct();
        $firstImage = $this->productImage($product);
        $secondImage = $this->productImage($product);
        $video = $this->platformVideo($product);

        $this->mediaFacade->reorderProductMedia($product->getId(), [
            ['type' => 'video', 'id' => $video->getId()],
            ['type' => 'image', 'id' => $secondImage->getId()],
            ['type' => 'image', 'id' => $firstImage->getId()],
        ]);

        self::assertSame(1, ProductVideoQuery::create()->findPk($video->getId())?->getPosition());
        self::assertSame(2, ProductImageQuery::create()->findPk($secondImage->getId())?->getPosition());
        self::assertSame(3, ProductImageQuery::create()->findPk($firstImage->getId())?->getPosition());
    }

    public function testAReorderNamingTheMediumOfAnotherProductIsRefusedAndWritesNothing(): void
    {
        $product = $this->createProduct();
        $image = $this->productImage($product);
        $video = $this->platformVideo($product);
        $foreignVideo = $this->platformVideo($this->createProduct());

        try {
            $this->mediaFacade->reorderProductMedia($product->getId(), [
                ['type' => 'video', 'id' => $foreignVideo->getId()],
                ['type' => 'image', 'id' => $image->getId()],
            ]);
            self::fail('A medium of another product must be refused.');
        } catch (\InvalidArgumentException) {
        }

        self::assertSame(1, ProductImageQuery::create()->findPk($image->getId())?->getPosition());
        self::assertSame(2, ProductVideoQuery::create()->findPk($video->getId())?->getPosition());
        self::assertSame(1, ProductVideoQuery::create()->findPk($foreignVideo->getId())?->getPosition());
    }

    public function testAReorderNamingAMediumTwiceIsRefused(): void
    {
        $product = $this->createProduct();
        $image = $this->productImage($product);
        $video = $this->platformVideo($product);

        $this->expectException(\InvalidArgumentException::class);

        $this->mediaFacade->reorderProductMedia($product->getId(), [
            ['type' => 'video', 'id' => $video->getId()],
            ['type' => 'video', 'id' => $video->getId()],
        ]);

        self::assertSame(1, ProductImageQuery::create()->findPk($image->getId())?->getPosition());
    }

    public function testDeletingAnImageClosesTheGapInTheSharedSequence(): void
    {
        $product = $this->createProduct();
        $firstImage = $this->productImage($product);
        $video = $this->platformVideo($product);
        $secondImage = $this->productImage($product);

        $firstImage->delete();

        // The video was second and is now first: it does not fall behind the
        // image that followed it, which a one-table renumbering would have caused.
        self::assertSame(1, ProductVideoQuery::create()->findPk($video->getId())?->getPosition());
        self::assertSame(2, ProductImageQuery::create()->findPk($secondImage->getId())?->getPosition());
    }

    public function testMovingAnImageThroughTheFacadeKeepsOneSequenceWithTheVideos(): void
    {
        $product = $this->createProduct();
        $firstImage = $this->productImage($product);
        $video = $this->platformVideo($product);
        $secondImage = $this->productImage($product);

        $this->mediaFacade->updateImagePosition($secondImage, 1, UpdatePositionEvent::POSITION_ABSOLUTE);

        self::assertSame(1, ProductImageQuery::create()->findPk($secondImage->getId())?->getPosition());
        self::assertSame(2, ProductImageQuery::create()->findPk($firstImage->getId())?->getPosition());
        self::assertSame(3, ProductVideoQuery::create()->findPk($video->getId())?->getPosition(), 'The video moves down with the rest.');

        $this->mediaFacade->updateVideoPosition($video, 1, UpdatePositionEvent::POSITION_ABSOLUTE);

        self::assertSame(1, ProductVideoQuery::create()->findPk($video->getId())?->getPosition());
        self::assertSame(2, ProductImageQuery::create()->findPk($secondImage->getId())?->getPosition());
        self::assertSame(3, ProductImageQuery::create()->findPk($firstImage->getId())?->getPosition());
    }

    public function testAReorderLeavingAMediumOutIsRefused(): void
    {
        $product = $this->createProduct();
        $this->productImage($product);
        $video = $this->platformVideo($product);

        $this->expectException(\InvalidArgumentException::class);

        $this->mediaFacade->reorderProductMedia($product->getId(), [
            ['type' => 'video', 'id' => $video->getId()],
        ]);
    }

    private function productImage(Product $product): ProductImage
    {
        $image = $this->factory->productImage($product);
        $this->trackFileForCleanup($image->getUploadDir().DS.$image->getFile());

        return $image;
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
