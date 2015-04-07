<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Template;

/**
 * Class TemplateHelper
 * @author Franck Allimant <franck@cqfdev.fr>
 * @package Thelia\Core\Template
 *
 * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead. This class will be DELETED in 2.3
 */
class TemplateHelper implements TemplateHelperInterface
{
    /** This is a singleton */
    private static $instance = null;

    /** @var TemplateHelperInterface  */
    private $templateHelper;

    private function __construct(TemplateHelperInterface $templateHelper)
    {
        $this->templateHelper = $templateHelper;

        self::$instance = $this;
    }

    /**
     * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead.
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            throw new \LogicException("This class should be initialized before getInstance() call.");
        }

        return self::$instance;
    }

    public static function init(TemplateHelperInterface $templateHelper)
    {
        self::$instance = new TemplateHelper($templateHelper);
    }
    /**
     * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead.
     */
    public function getActiveMailTemplate()
    {
        return $this->templateHelper->getActiveMailTemplate();
    }

    /**
     * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead.
     */
    public function isActive(TemplateDefinition $tplDefinition)
    {
        return $this->templateHelper->isActive($tplDefinition);
    }

    /**
     * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead.
     */
    public function getActivePdfTemplate()
    {
        return $this->templateHelper->getActivePdfTemplate();
    }

    /**
     * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead.
     */
    public function getActiveAdminTemplate()
    {
        return $this->templateHelper->getActiveAdminTemplate();
    }

    /**
     * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead.
     */
    public function getActiveFrontTemplate()
    {
        return $this->templateHelper->getActiveFrontTemplate();
    }

    /**
     * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead.
     */
    public function getStandardTemplateDefinitions()
    {
        return $this->templateHelper->getStandardTemplateDefinitions();
    }

    /**
     * @deprecated use TheliaTemplateHelper service (thelia.template_helper) instead.
     */
    public function getList($templateType, $base = THELIA_TEMPLATE_DIR)
    {
        return $this->templateHelper->getList($templateType, $base);
    }
}
