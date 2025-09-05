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
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Base\BrandDocument as BaseBrandDocument;
use Thelia\Model\Breadcrumb\BrandBreadcrumbTrait;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Tools\PositionManagementTrait;

class BrandDocument extends BaseBrandDocument implements BreadcrumbInterface, FileModelInterface
{
    use BrandBreadcrumbTrait;
    use PositionManagementTrait;

    /**
     * Calculate next position relative to our parent.
     */
    protected function addCriteriaToPositionQuery(BrandDocumentQuery $query): void
    {
        $query->filterByBrandId($this->getBrandId());
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function preDelete(?ConnectionInterface $con = null): true
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'brand_id' => $this->getBrandId(),
            ],
        );

        return true;
    }

    public function setParentId(int $parentId): static
    {
        $this->setBrandId($parentId);

        return $this;
    }

    public function getParentId(): int
    {
        return $this->getBrandId();
    }

    public function getParentFileModel(): FileModelParentInterface
    {
        return new Brand();
    }

    /**
     * Get the ID of the form used to change this object information.
     */
    public function getUpdateFormId(): string
    {
        return AdminForm::BRAND_DOCUMENT_MODIFICATION;
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir(): string
    {
        $uploadDir = ConfigQuery::read('documents_library_path');
        $uploadDir = null === $uploadDir ? THELIA_LOCAL_DIR.'media'.DS.'documents' : THELIA_ROOT.$uploadDir;

        return $uploadDir.DS.'brand';
    }

    public function getRedirectionUrl(): string
    {
        return '/admin/brand/update/'.$this->getBrandId();
    }

    /**
     * Get the Query instance for this object.
     */
    public function getQueryInstance(): ModelCriteria
    {
        return BrandDocumentQuery::create();
    }

    public function getFile(): string
    {
        return parent::getFile();
    }

    public function getId(): int
    {
        return parent::getId();
    }

    public function getTitle(): string
    {
        return parent::getTitle();
    }
}
