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
use Symfony\Component\Routing\Router;
use Thelia\Files\FileModelInterface;
use Thelia\Files\FileModelParentInterface;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Base\ProductImage as BaseProductImage;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Breadcrumb\CatalogBreadcrumbTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class ProductImage extends BaseProductImage implements BreadcrumbInterface, FileModelInterface
{
    use CatalogBreadcrumbTrait;
    use PositionManagementTrait;

    /**
     * Calculate next position relative to our parent.
     */
    protected function addCriteriaToPositionQuery(ProductImageQuery $query): void
    {
        $query->filterByProduct($this->getProduct());
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function setParentId($parentId): static
    {
        $this->setProductId($parentId);

        return $this;
    }

    public function getParentId(): int
    {
        return $this->getProductId();
    }

    public function preDelete(?ConnectionInterface $con = null): bool
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'product_id' => $this->getProductId(),
            ],
        );

        return true;
    }

    public function getBreadcrumb(Router $router, $tab, $locale): array
    {
        return $this->getProductBreadcrumb($router, $tab, $locale);
    }

    public function getParentFileModel(): FileModelParentInterface
    {
        return new Product();
    }

    /**
     * Get the ID of the form used to change this object information.
     *
     * @return BaseForm the form
     */
    public function getUpdateFormId(): string
    {
        return AdminForm::PRODUCT_IMAGE_MODIFICATION;
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir(): string
    {
        $uploadDir = ConfigQuery::read('images_library_path');
        $uploadDir = null === $uploadDir ? THELIA_LOCAL_DIR.'media'.DS.'images' : THELIA_ROOT.$uploadDir;

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
        return ProductImageQuery::create();
    }

    public function getFile(): string
    {
        return parent::getFile();
    }
}
