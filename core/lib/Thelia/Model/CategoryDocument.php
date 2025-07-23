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
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Base\CategoryDocument as BaseCategoryDocument;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Breadcrumb\CatalogBreadcrumbTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class CategoryDocument extends BaseCategoryDocument implements BreadcrumbInterface, FileModelInterface
{
    use CatalogBreadcrumbTrait;
    use PositionManagementTrait;

    /**
     * Calculate next position relative to our parent.
     */
    protected function addCriteriaToPositionQuery(CategoryDocumentQuery $query): void
    {
        $query->filterByCategory($this->getCategory());
    }

    public function preInsert(?ConnectionInterface $con = null): true
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function setParentId($parentId): static
    {
        $this->setCategoryId($parentId);

        return $this;
    }

    public function getParentId(): int
    {
        return $this->getCategoryId();
    }

    public function preDelete(?ConnectionInterface $con = null): true
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'category_id' => $this->getCategoryId(),
            ],
        );

        return true;
    }

    public function getBreadcrumb(Router $router, $tab, $locale): array
    {
        return $this->getCategoryBreadcrumb($router, $tab, $locale);
    }

    /**
     * @return FileModelParentInterface the parent file model
     */
    public function getParentFileModel(): FileModelParentInterface
    {
        return new Category();
    }

    /**
     * Get the ID of the form used to change this object information.
     */
    public function getUpdateFormId(): string
    {
        return AdminForm::CATEGORY_DOCUMENT_MODIFICATION;
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir(): string
    {
        $uploadDir = ConfigQuery::read('documents_library_path');
        $uploadDir = null === $uploadDir ? THELIA_LOCAL_DIR.'media'.DS.'documents' : THELIA_ROOT.$uploadDir;

        return $uploadDir.DS.'category';
    }

    /**
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl(): string
    {
        return '/admin/categories/update?category_id='.$this->getCategoryId();
    }

    /**
     * Get the Query instance for this object.
     */
    public function getQueryInstance(): ModelCriteria
    {
        return CategoryDocumentQuery::create();
    }

    public function getFile(): string
    {
        return parent::getFile();
    }
}
