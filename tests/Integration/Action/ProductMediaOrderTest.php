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

use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Domain\Media\MediaFacade;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Model\Map\ProductVideoTableMap;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductVideoQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The images and the videos of a product share one sequence of positions: a
 * step up or down swaps a medium with its immediate neighbour whatever table it
 * lives in, and a deletion closes the gap with a bounded number of writes.
 */
final class ProductMediaOrderTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;
    use RecordsSqlQueries;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testMovingAnImageUpSwapsItWithTheVideoRightBeforeIt(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $firstImage = $this->image($product);
        $video = $this->factory->productVideo($product);
        $lastImage = $this->image($product);

        $this->assertPositions([1, 2, 3], $firstImage->getId(), $video->getId(), $lastImage->getId());

        $this->getService(MediaFacade::class)->updateImagePosition($lastImage, 0, UpdatePositionEvent::POSITION_UP);

        $this->assertPositions([1, 3, 2], $firstImage->getId(), $video->getId(), $lastImage->getId());
    }

    public function testMovingAVideoDownSwapsItWithTheImageRightAfterIt(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $firstImage = $this->image($product);
        $video = $this->factory->productVideo($product);
        $lastImage = $this->image($product);

        $this->getService(MediaFacade::class)->updateVideoPosition($video, 0, UpdatePositionEvent::POSITION_DOWN);

        $this->assertPositions([1, 3, 2], $firstImage->getId(), $video->getId(), $lastImage->getId());
    }

    public function testTheFirstMediumDoesNotMoveUp(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $firstImage = $this->image($product);
        $video = $this->factory->productVideo($product);
        $lastImage = $this->image($product);

        $this->getService(MediaFacade::class)->updateImagePosition($firstImage, 0, UpdatePositionEvent::POSITION_UP);

        $this->assertPositions([1, 2, 3], $firstImage->getId(), $video->getId(), $lastImage->getId());
    }

    public function testDeletingImagesWritesOneStatementPerTablePerDeletionAtMost(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $images = [];
        for ($i = 0; $i < 8; ++$i) {
            $images[] = $this->image($product);
        }

        $statements = $this->recordSqlQueries(static function () use ($images): void {
            foreach ($images as $image) {
                $image->delete();
            }
        });

        $updates = \count(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/^\s*UPDATE\s+`?product_image`?\s/i', $statement),
        ));

        self::assertLessThanOrEqual(
            \count($images),
            $updates,
            \sprintf('Deleting %d images ran %d UPDATE statements on product_image.', \count($images), $updates),
        );
    }

    public function testADeletionClosesTheGapInBothTables(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $firstImage = $this->image($product);
        $video = $this->factory->productVideo($product);
        $lastImage = $this->image($product);

        $firstImage->delete();

        ProductImageTableMap::clearInstancePool();
        ProductVideoTableMap::clearInstancePool();
        self::assertSame(1, ProductVideoQuery::create()->findPk($video->getId())?->getPosition());
        self::assertSame(2, ProductImageQuery::create()->findPk($lastImage->getId())?->getPosition());
    }

    public function testMediaDeletedOneAfterTheOtherLeaveNoGapBehind(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $firstImage = $this->image($product);
        $video = $this->factory->productVideo($product);
        $lastImage = $this->image($product);

        // The video model still holds position 2 when it is deleted: the first
        // deletion moved it up in the database only.
        $firstImage->delete();
        $video->delete();

        ProductImageTableMap::clearInstancePool();
        self::assertSame(1, ProductImageQuery::create()->findPk($lastImage->getId())?->getPosition());
    }

    private function image(\Thelia\Model\Product $product): \Thelia\Model\ProductImage
    {
        $image = $this->factory->productImage($product);
        $this->trackFileForCleanup($image->getUploadDir().DS.$image->getFile());

        return $image;
    }

    /**
     * @param array{int, int, int} $expected the positions of the first image, the video and the last image
     */
    private function assertPositions(array $expected, int $firstImageId, int $videoId, int $lastImageId): void
    {
        ProductImageTableMap::clearInstancePool();
        ProductVideoTableMap::clearInstancePool();

        self::assertSame($expected, [
            ProductImageQuery::create()->findPk($firstImageId)?->getPosition(),
            ProductVideoQuery::create()->findPk($videoId)?->getPosition(),
            ProductImageQuery::create()->findPk($lastImageId)?->getPosition(),
        ]);
    }
}
