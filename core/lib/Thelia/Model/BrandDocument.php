<?php

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
use Thelia\Files\FileModelInterface;
use Thelia\Files\FileModelParentInterface;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Base\BrandDocument as BaseBrandDocument;
use Thelia\Model\Breadcrumb\BrandBreadcrumbTrait;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Tools\PositionManagementTrait;

class BrandDocument extends BaseBrandDocument implements BreadcrumbInterface, FileModelInterface
{
    use PositionManagementTrait;
    use BrandBreadcrumbTrait;

    /**
     * Calculate next position relative to our parent.
     *
     * @param BrandDocumentQuery $query
     */
    protected function addCriteriaToPositionQuery($query): void
    {
        $query->filterByBrandId($this->getBrandId());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'brand_id' => $this->getBrandId(),
            ]
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setParentId($parentId)
    {
        $this->setBrandId($parentId);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getParentId()
    {
        return $this->getBrandId();
    }

    /**
     * @return FileModelParentInterface the parent file model
     */
    public function getParentFileModel()
    {
        return new Brand();
    }

    /**
     * Get the ID of the form used to change this object information.
     *
     * @return BaseForm the form
     */
    public function getUpdateFormId()
    {
        return AdminForm::BRAND_DOCUMENT_MODIFICATION;
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir()
    {
        $uploadDir = ConfigQuery::read('documents_library_path');
        if ($uploadDir === null) {
            $uploadDir = THELIA_LOCAL_DIR.'media'.DS.'documents';
        } else {
            $uploadDir = THELIA_ROOT.$uploadDir;
        }

        return $uploadDir.DS.'brand';
    }

    /**
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl()
    {
        return '/admin/brand/update/'.$this->getBrandId();
    }

    /**
     * Get the Query instance for this object.
     *
     * @return ModelCriteria
     */
    public function getQueryInstance()
    {
        return BrandDocumentQuery::create();
    }
}
