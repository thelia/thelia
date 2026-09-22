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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\File\FileModelInterface;
use Thelia\Core\File\FileModelParentInterface;
use Thelia\Domain\Media\ProductMediaOrder;
use Thelia\Domain\Media\Video\VideoProvider;
use Thelia\Model\Base\ProductVideo as BaseProductVideo;
use Thelia\Model\Tools\PositionManagementTrait;

/**
 * A video shown on a product sheet, either played from a platform or hosted by
 * the shop.
 *
 * It is a file model like the product images are, so the upload path, the
 * positions and the deletion of the stored file all work the way a merchant
 * already knows. What it never goes through is the image pipeline: a video is
 * copied where it belongs and nothing resizes it.
 */
class ProductVideo extends BaseProductVideo implements FileModelInterface
{
    use PositionManagementTrait;

    /**
     * The video library, which the shop may have moved elsewhere.
     */
    public const LIBRARY_PATH_VARIABLE = 'videos_library_path';

    public const DEFAULT_LIBRARY_PATH = 'local/media/videos';

    /**
     * Calculate next position relative to our parent.
     */
    protected function addCriteriaToPositionQuery(ProductVideoQuery $query): void
    {
        $query->filterByProduct($this->getProduct());
    }

    /**
     * The images and the videos of a product share one sequence: a new medium goes
     * after every medium the product already shows, whichever table it lives in.
     */
    public function getNextPosition(): int|float
    {
        return (new ProductMediaOrder())->nextPosition((int) $this->getProductId());
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    /**
     * Puts this medium at the given rank among the images and the videos of its
     * product: the sequence is shared, so the trait's one-table shift would leave
     * two media on the same position.
     */
    public function changeAbsolutePosition($newPosition): void
    {
        if (null === $newPosition || (int) $newPosition <= 0) {
            return;
        }

        (new ProductMediaOrder())->moveTo($this, (int) $newPosition);
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        // Closes the gap in the sequence the images and the videos of the product
        // share; the trait's reorderBeforeDelete() would only close it in this table.
        (new ProductMediaOrder())->compact((int) $this->getProductId());
    }

    public function setParentId(int $parentId): static
    {
        $this->setProductId($parentId);

        return $this;
    }

    public function getParentId(): int
    {
        return $this->getProductId();
    }

    public function getParentFileModel(): FileModelParentInterface
    {
        return new Product();
    }

    /**
     * Get the ID of the form used to change this object information.
     */
    public function getUpdateFormId(): string
    {
        return 'thelia.admin.product.video.modification';
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir(): string
    {
        $uploadDir = ConfigQuery::read(self::LIBRARY_PATH_VARIABLE);
        $uploadDir = null === $uploadDir ? THELIA_ROOT.self::DEFAULT_LIBRARY_PATH : THELIA_ROOT.$uploadDir;

        return $uploadDir.DS.'product';
    }

    /**
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl(): string
    {
        return '/admin/products/update?product_id='.$this->getProductId();
    }

    /**
     * Get the Query instance for this object.
     */
    public function getQueryInstance(): ModelCriteria
    {
        return ProductVideoQuery::create();
    }

    /**
     * A platform video has no stored file: the empty string is what the file
     * interface can say, and what every caller of it already handles.
     */
    public function getFile(): string
    {
        return (string) parent::getFile();
    }

    /**
     * Whether the shop stores the video itself, as opposed to playing it from a
     * platform.
     */
    public function isHostedFile(): bool
    {
        return VideoProvider::File->value === $this->getProvider() && '' !== $this->getFile();
    }
}
