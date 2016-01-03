<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Files\FileModelInterface;
use Thelia\Files\FileModelParentInterface;
use Thelia\Form\BaseForm;
use Thelia\Form\Brand\BrandDocumentModification;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Base\BrandDocument as BaseBrandDocument;
use Thelia\Model\Breadcrumb\BrandBreadcrumbTrait;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class BrandDocument extends BaseBrandDocument implements BreadcrumbInterface, FileModelInterface
{
    use ModelEventDispatcherTrait;
    use PositionManagementTrait;
    use BrandBreadcrumbTrait;

    /**
     * Calculate next position relative to our parent
     *
     * @param BrandDocumentQuery $query
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByBrandId($this->getBrandId());
    }

    /**
     * @inheritDoc
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->reorderBeforeDelete(
            array(
                "brand_id" => $this->getBrandId(),
            )
        );

        return true;
    }

    /**
     * @inheritDoc
     */
    public function setParentId($parentId)
    {
        $this->setBrandId($parentId);

        return $this;
    }

    /**
     * @inheritDoc
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
     * Get the ID of the form used to change this object information
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
            $uploadDir = THELIA_LOCAL_DIR . 'media' . DS . 'documents';
        } else {
            $uploadDir = THELIA_ROOT . $uploadDir;
        }

        return $uploadDir . DS . 'brand';
    }

    /**
     * @param int $objectId the ID of the object
     *
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl()
    {
        return '/admin/brand/update/' . $this->getBrandId();
    }

    /**
     * Get the Query instance for this object
     *
     * @return ModelCriteria
     */
    public function getQueryInstance()
    {
        return BrandDocumentQuery::create();
    }
}
