<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\Base\Module as BaseModule;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Module\BaseModuleInterface;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\PaymentModuleInterface;

class Module extends BaseModule
{
    use ModelEventDispatcherTrait;

    use PositionManagementTrait;

    const ADMIN_INCLUDES_DIRECTORY_NAME = "AdminIncludes";

    public function postSave(ConnectionInterface $con = null)
    {
        ModuleQuery::resetActivated();
    }

    public function getTranslationDomain()
    {
        return strtolower($this->getCode());
    }

    public function getAdminIncludesTranslationDomain()
    {
        return $this->getTranslationDomain().'.ai';
    }

    public function getAbsoluteBackOfficeTemplatePath($subdir)
    {
        return sprintf(
            "%s".DS."%s".DS."%s",
            $this->getAbsoluteTemplateBasePath(),
            TemplateDefinition::BACK_OFFICE_SUBDIR,
            $subdir
        );
    }

    public function getAbsoluteBackOfficeI18nTemplatePath($subdir)
    {
        return sprintf(
            "%s".DS."%s".DS."%s",
            $this->getAbsoluteI18nPath(),
            TemplateDefinition::BACK_OFFICE_SUBDIR,
            $subdir
        );
    }

    public function getBackOfficeTemplateTranslationDomain($templateName)
    {
        return $this->getTranslationDomain(). '.bo.' . $templateName;
    }

    public function getAbsoluteFrontOfficeTemplatePath($subdir)
    {
        return sprintf(
            "%s".DS."%s".DS."%s",
            $this->getAbsoluteTemplateBasePath(),
            TemplateDefinition::FRONT_OFFICE_SUBDIR,
            $subdir
        );
    }

    public function getAbsoluteFrontOfficeI18nTemplatePath($subdir)
    {
        return sprintf(
            "%s".DS."%s".DS."%s",
            $this->getAbsoluteI18nPath(),
            TemplateDefinition::FRONT_OFFICE_SUBDIR,
            $subdir
        );
    }

    public function getFrontOfficeTemplateTranslationDomain($templateName)
    {
        return $this->getTranslationDomain(). '.fo.' . $templateName;
    }

    public function getAbsolutePdfTemplatePath($subdir)
    {
        return sprintf(
            "%s".DS."%s".DS."%s",
            $this->getAbsoluteTemplateBasePath(),
            TemplateDefinition::PDF_SUBDIR,
            $subdir
        );
    }

    public function getAbsolutePdfI18nTemplatePath($subdir)
    {
        return sprintf(
            "%s".DS."%s".DS."%s",
            $this->getAbsoluteI18nPath(),
            TemplateDefinition::PDF_SUBDIR,
            $subdir
        );
    }

    public function getPdfTemplateTranslationDomain($templateName)
    {
        return $this->getTranslationDomain(). '.pdf.' . $templateName;
    }


    public function getAbsoluteEmailTemplatePath($subdir)
    {
        return sprintf(
            "%s".DS."%s".DS."%s",
            $this->getAbsoluteTemplateBasePath(),
            TemplateDefinition::EMAIL_SUBDIR,
            $subdir
        );
    }

    public function getAbsoluteEmailI18nTemplatePath($subdir)
    {
        return sprintf(
            "%s".DS."%s".DS."%s",
            $this->getAbsoluteI18nPath(),
            TemplateDefinition::EMAIL_SUBDIR,
            $subdir
        );
    }

    public function getEmailTemplateTranslationDomain($templateName)
    {
        return $this->getTranslationDomain(). '.email.' . $templateName;
    }

    /**
     * @return string the module's base directory path, relative to THELIA_MODULE_DIR
     */
    public function getBaseDir()
    {
        return ucfirst($this->getCode());
    }

    /**
     * @return string the module's base directory path, relative to THELIA_MODULE_DIR
     */
    public function getAbsoluteBaseDir()
    {
        return THELIA_MODULE_DIR . $this->getBaseDir();
    }

    /**
     * @return string the module's config directory path, relative to THELIA_MODULE_DIR
     */
    public function getConfigPath()
    {
        return $this->getBaseDir() . DS . "Config";
    }

    /**
     * @return string the module's config absolute directory path
     */
    public function getAbsoluteConfigPath()
    {
        return THELIA_MODULE_DIR . $this->getConfigPath();
    }

    /**
     * @return string the module's i18N directory path, relative to THELIA_MODULE_DIR
     */
    public function getI18nPath()
    {
        return $this->getBaseDir() . DS . "I18n";
    }

    /**
     * @return string the module's i18N absolute directory path
     */
    public function getAbsoluteI18nPath()
    {
        return THELIA_MODULE_DIR . $this->getI18nPath();
    }

    /**
     * @return string the module's AdminIncludes absolute directory path
     */
    public function getAbsoluteAdminIncludesPath()
    {
        return $this->getAbsoluteBaseDir() . DS . self::ADMIN_INCLUDES_DIRECTORY_NAME;
    }

    /**
     * @return string the module's AdminIncludes i18N absolute directory path
     */
    public function getAbsoluteAdminIncludesI18nPath()
    {
        return THELIA_MODULE_DIR . $this->getI18nPath() . DS . self::ADMIN_INCLUDES_DIRECTORY_NAME;
    }

    /**
     * Return the absolute path to the module's template directory
     *
      * @return string a path
     */
    public function getAbsoluteTemplateBasePath()
    {
        return $this->getAbsoluteBaseDir() . DS . 'templates';
    }

    /**
     * Return the absolute path to one of the module's template directories
     *
     * @param  int    $templateSubdirName the name of the, probably one of TemplateDefinition::xxx_SUBDIR constants
     * @return string a path
     */
    public function getAbsoluteTemplateDirectoryPath($templateSubdirName)
    {
        return $this->getAbsoluteTemplateBasePath() .DS. $templateSubdirName;
    }

    /**
     * @return true if this module is a delivery module
     */
    public function isDeliveryModule()
    {
        $moduleReflection = new \ReflectionClass($this->getFullNamespace());

        return $moduleReflection->implementsInterface("Thelia\Module\DeliveryModuleInterface");
    }

    /**
     * @return true if this module is a payment module
     */
    public function isPayementModule()
    {
        $moduleReflection = new \ReflectionClass($this->getFullNamespace());

        return $moduleReflection->implementsInterface("Thelia\Module\PaymentModuleInterface");
    }

    /**
     * @return bool true if the module image has been deployed, false otherwise.
     */
    public function isModuleImageDeployed()
    {
        return ModuleImageQuery::create()->filterByModuleId($this->getId())->count() > 0;
    }

    /**
     * @param  ContainerInterface        $container the Thelia container
     * @return BaseModuleInterface       a module instance
     * @throws \InvalidArgumentException if the module could not be found in the container/
     */
    public function getModuleInstance(ContainerInterface $container)
    {
        $instance = $container->get(sprintf('module.%s', $this->getCode()));

        if ($instance == null) {
            throw new \InvalidArgumentException(sprintf('Undefined module in container: "%s"', $this->getCode()));
        }

        return $instance;
    }

    /**
     * @param  ContainerInterface        $container the Thelia container
     * @return DeliveryModuleInterface   a module instance
     * @throws \InvalidArgumentException if the module could not be found in the container/
     */
    public function getDeliveryModuleInstance(ContainerInterface $container)
    {
        $instance = $this->getModuleInstance($container);

        if (! $instance instanceof DeliveryModuleInterface) {
            throw new \InvalidArgumentException(sprintf('Module "%s" is not a payment module', $this->getCode()));
        }

        return $instance;
    }

    /**
     * @param  ContainerInterface        $container the Thelia container
     * @return PaymentModuleInterface    a payment module instance
     * @throws \InvalidArgumentException if the module is not found or not a payment module
     */
    public function getPaymentModuleInstance(ContainerInterface $container)
    {
        $instance = $this->getModuleInstance($container);

        if (! $instance instanceof PaymentModuleInterface) {
            throw new \InvalidArgumentException(sprintf('Module "%s" is not a payment module', $this->getCode()));
        }

        return $instance;
    }
    /**
     * @return \Thelia\Module\BaseModule a new module instance.
     */
    public function createInstance()
    {
        $moduleClass = new \ReflectionClass($this->getFullNamespace());

        return $moduleClass->newInstance();
    }

    /**
     * Calculate next position relative to module type
     *
     * @param ModuleQuery $query
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByType($this->getType());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }
}
