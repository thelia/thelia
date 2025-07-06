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
use Thelia\Model\Base\FolderImage as BaseFolderImage;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Breadcrumb\FolderBreadcrumbTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class FolderImage extends BaseFolderImage implements BreadcrumbInterface, FileModelInterface
{
    use FolderBreadcrumbTrait;
    use PositionManagementTrait;

    /**
     * Calculate next position relative to our parent.
     *
     * @param FolderImageQuery $query
     */
    protected function addCriteriaToPositionQuery($query): void
    {
        $query->filterByFolder($this->getFolder());
    }

    public function preInsert(?ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function setParentId($parentId)
    {
        $this->setFolderId($parentId);

        return $this;
    }

    public function getParentId()
    {
        return $this->getFolderId();
    }

    public function preDelete(?ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'folder_id' => $this->getFolderId(),
            ]
        );

        return true;
    }

    public function getBreadcrumb(Router $router, $tab, $locale)
    {
        return $this->getFolderBreadcrumb($router, $tab, $locale);
    }

    /**
     * @return FileModelParentInterface the parent file model
     */
    public function getParentFileModel()
    {
        return new Folder();
    }

    /**
     * Get the ID of the form used to change this object information.
     *
     * @return BaseForm the form
     */
    public function getUpdateFormId()
    {
        return AdminForm::FOLDER_IMAGE_MODIFICATION;
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir()
    {
        $uploadDir = ConfigQuery::read('images_library_path');
        $uploadDir = $uploadDir === null ? THELIA_LOCAL_DIR.'media'.DS.'images' : THELIA_ROOT.$uploadDir;

        return $uploadDir.DS.'folder';
    }

    /**
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl()
    {
        return '/admin/folders/update/'.$this->getFolderId();
    }

    /**
     * Get the Query instance for this object.
     *
     * @return ModelCriteria
     */
    public function getQueryInstance()
    {
        return FolderImageQuery::create();
    }
}
