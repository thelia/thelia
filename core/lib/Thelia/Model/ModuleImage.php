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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Router;
use Thelia\Core\Translation\Translator;
use Thelia\Files\FileModelInterface;
use Thelia\Files\FileModelParentInterface;
use Thelia\Form\BaseForm;
use Thelia\Model\Base\ModuleImage as BaseModuleImage;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Tools\URL;

class ModuleImage extends BaseModuleImage implements FileModelInterface
{
    use PositionManagementTrait;

    /**
     * Set file parent id.
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->setModuleId($parentId);

        return $this;
    }

    /**
     * Get file parent id.
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getModuleId();
    }

    /**
     * @return FileModelParentInterface the parent file model
     */
    public function getParentFileModel()
    {
        return new Module();
    }

    /**
     * Get the ID of the form used to change this object information.
     *
     * @return BaseForm the form
     */
    public function getUpdateFormId()
    {
        return 'thelia.admin.module.image.modification';
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir()
    {
        return THELIA_LOCAL_DIR.'media'.DS.'images'.DS.'module';
    }

    /**
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl()
    {
        return '/admin/module/update/'.$this->getModuleId();
    }

    /**
     * Get the Query instance for this object.
     *
     * @return ModelCriteria
     */
    public function getQueryInstance()
    {
        return ModuleImageQuery::create();
    }

    public function getBreadcrumb(Router $router, ContainerInterface $container, $tab, $locale)
    {
        $translator = Translator::getInstance();

        /** @var Module */
        $module = $this->getModule();
        $breadcrumb = [
            $translator->trans('Home') => URL::getInstance()->absoluteUrl('/admin'),
            $translator->trans('Module') => $router->generate('admin.module', [], Router::ABSOLUTE_URL),
        ];

        $module->setLocale($locale);

        $breadcrumb[$module->getTitle()] = sprintf(
            '%s?current_tab=%s',
            $router->generate(
                'admin.module.update',
                ['module_id' => $module->getId()],
                Router::ABSOLUTE_URL
            ),
            $tab
        );

        return $breadcrumb;
    }
}
