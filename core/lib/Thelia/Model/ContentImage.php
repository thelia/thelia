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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Router;
use Thelia\Files\FileModelInterface;
use Thelia\Files\FileModelParentInterface;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Base\ContentImage as BaseContentImage;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Breadcrumb\FolderBreadcrumbTrait;

class ContentImage extends BaseContentImage implements BreadcrumbInterface, FileModelInterface
{
    use \Thelia\Model\Tools\PositionManagementTrait;
    use FolderBreadcrumbTrait;

    /**
     * Calculate next position relative to our parent.
     *
     * @param ContentImageQuery $query
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByContent($this->getContent());
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

    /**
     * {@inheritdoc}
     */
    public function setParentId($parentId)
    {
        $this->setContentId($parentId);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParentId()
    {
        return $this->getContentId();
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'content_id' => $this->getContentId(),
            ]
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getBreadcrumb(Router $router, ContainerInterface $container, $tab, $locale)
    {
        return $this->getContentBreadcrumb($router, $container, $tab, $locale);
    }

    /**
     * @return FileModelParentInterface the parent file model
     */
    public function getParentFileModel()
    {
        return new Content();
    }

    /**
     * Get the ID of the form used to change this object information.
     *
     * @return BaseForm the form
     */
    public function getUpdateFormId()
    {
        return AdminForm::CONTENT_IMAGE_MODIFICATION;
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir()
    {
        $uploadDir = ConfigQuery::read('images_library_path');
        if ($uploadDir === null) {
            $uploadDir = THELIA_LOCAL_DIR.'media'.DS.'images';
        } else {
            $uploadDir = THELIA_ROOT.$uploadDir;
        }

        return $uploadDir.DS.'content';
    }

    /**
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl()
    {
        return '/admin/content/update/'.$this->getContentId();
    }

    /**
     * Get the Query instance for this object.
     *
     * @return ModelCriteria
     */
    public function getQueryInstance()
    {
        return ContentImageQuery::create();
    }
}
