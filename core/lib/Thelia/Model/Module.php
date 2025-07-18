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

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\Base\Module as BaseModule;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Module\BaseModuleInterface;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\DeliveryModuleWithStateInterface;
use Thelia\Module\PaymentModuleInterface;

class Module extends BaseModule
{
    use PositionManagementTrait;

    public const ADMIN_INCLUDES_DIRECTORY_NAME = 'AdminIncludes';

    public function postSave(?ConnectionInterface $con = null): void
    {
        ModuleQuery::resetActivated();

        parent::postSave();
    }

    public function getTranslationDomain(): string
    {
        return strtolower($this->getCode());
    }

    public function getAdminIncludesTranslationDomain(): string
    {
        return $this->getTranslationDomain().'.ai';
    }

    public function getAbsoluteBackOfficeTemplatePath(string $subdir): string
    {
        return \sprintf(
            '%s'.DS.'%s'.DS.'%s',
            $this->getAbsoluteTemplateBasePath(),
            TemplateDefinition::BACK_OFFICE_SUBDIR,
            $subdir,
        );
    }

    public function getAbsoluteBackOfficeI18nTemplatePath(string $subdir): string
    {
        return \sprintf(
            '%s'.DS.'%s'.DS.'%s',
            $this->getAbsoluteI18nPath(),
            TemplateDefinition::BACK_OFFICE_SUBDIR,
            $subdir,
        );
    }

    public function getBackOfficeTemplateTranslationDomain(string $templateName): string
    {
        return $this->getTranslationDomain().'.bo.'.$templateName;
    }

    public function getAbsoluteFrontOfficeTemplatePath(string $subdir): string
    {
        return \sprintf(
            '%s'.DS.'%s'.DS.'%s',
            $this->getAbsoluteTemplateBasePath(),
            TemplateDefinition::FRONT_OFFICE_SUBDIR,
            $subdir,
        );
    }

    public function getAbsoluteFrontOfficeI18nTemplatePath(string $subdir): string
    {
        return \sprintf(
            '%s'.DS.'%s'.DS.'%s',
            $this->getAbsoluteI18nPath(),
            TemplateDefinition::FRONT_OFFICE_SUBDIR,
            $subdir,
        );
    }

    public function getFrontOfficeTemplateTranslationDomain(string $templateName): string
    {
        return $this->getTranslationDomain().'.fo.'.$templateName;
    }

    public function getAbsolutePdfTemplatePath(string $subdir): string
    {
        return \sprintf(
            '%s'.DS.'%s'.DS.'%s',
            $this->getAbsoluteTemplateBasePath(),
            TemplateDefinition::PDF_SUBDIR,
            $subdir,
        );
    }

    public function getAbsolutePdfI18nTemplatePath(string $subdir): string
    {
        return \sprintf(
            '%s'.DS.'%s'.DS.'%s',
            $this->getAbsoluteI18nPath(),
            TemplateDefinition::PDF_SUBDIR,
            $subdir,
        );
    }

    public function getPdfTemplateTranslationDomain(string $templateName): string
    {
        return $this->getTranslationDomain().'.pdf.'.$templateName;
    }

    public function getAbsoluteEmailTemplatePath(string $subdir): string
    {
        return \sprintf(
            '%s'.DS.'%s'.DS.'%s',
            $this->getAbsoluteTemplateBasePath(),
            TemplateDefinition::EMAIL_SUBDIR,
            $subdir,
        );
    }

    public function getAbsoluteEmailI18nTemplatePath(string $subdir): string
    {
        return \sprintf(
            '%s'.DS.'%s'.DS.'%s',
            $this->getAbsoluteI18nPath(),
            TemplateDefinition::EMAIL_SUBDIR,
            $subdir,
        );
    }

    public function getEmailTemplateTranslationDomain(string $templateName): string
    {
        return $this->getTranslationDomain().'.email.'.$templateName;
    }

    /**
     * @return string the module's base directory path, relative to THELIA_MODULE_DIR
     */
    public function getBaseDir(): string
    {
        return ucfirst($this->getCode());
    }

    /**
     * @return string the module's base directory path, relative to THELIA_MODULE_DIR
     */
    public function getAbsoluteBaseDir(): string
    {
        return $this->getModuleDir().$this->getBaseDir();
    }

    /**
     * @return string the module's config directory path, relative to THELIA_MODULE_DIR
     */
    public function getConfigPath(): string
    {
        return $this->getBaseDir().DS.'Config';
    }

    /**
     * @return string the module's config absolute directory path
     */
    public function getAbsoluteConfigPath(): string
    {
        return $this->getModuleDir().$this->getConfigPath();
    }

    /**
     * @return string the module's i18N directory path, relative to THELIA_MODULE_DIR
     */
    public function getI18nPath(): string
    {
        return $this->getBaseDir().DS.'I18n';
    }

    /**
     * @return string the module's i18N absolute directory path
     */
    public function getAbsoluteI18nPath(): string
    {
        return $this->getModuleDir().$this->getI18nPath();
    }

    /**
     * @return string the module's AdminIncludes absolute directory path
     */
    public function getAbsoluteAdminIncludesPath(): string
    {
        return $this->getAbsoluteBaseDir().DS.self::ADMIN_INCLUDES_DIRECTORY_NAME;
    }

    /**
     * @return string the module's AdminIncludes i18N absolute directory path
     */
    public function getAbsoluteAdminIncludesI18nPath(): string
    {
        return $this->getModuleDir().$this->getI18nPath().DS.self::ADMIN_INCLUDES_DIRECTORY_NAME;
    }

    /**
     * Return the absolute path to the module's template directory.
     *
     * @return string a path
     */
    public function getAbsoluteTemplateBasePath(): string
    {
        return $this->getAbsoluteBaseDir().DS.'templates';
    }

    /**
     * Return the absolute path to one of the module's template directories.
     *
     * @param string $templateSubdirName the name of the, probably one of TemplateDefinition::xxx_SUBDIR constants
     *
     * @return string a path
     */
    public function getAbsoluteTemplateDirectoryPath(string $templateSubdirName): string
    {
        return $this->getAbsoluteTemplateBasePath().DS.$templateSubdirName;
    }

    /**
     * @return true if this module is a delivery module
     */
    public function isDeliveryModule(): bool
    {
        $moduleReflection = new \ReflectionClass($this->getFullNamespace());

        return $moduleReflection->implementsInterface(DeliveryModuleInterface::class)
            || $moduleReflection->implementsInterface(DeliveryModuleWithStateInterface::class);
    }

    /**
     * @return bool true if this module is a payment module
     */
    public function isPayementModule(): bool
    {
        $moduleReflection = new \ReflectionClass($this->getFullNamespace());

        return $moduleReflection->implementsInterface(PaymentModuleInterface::class);
    }

    /**
     * @return bool true if the module image has been deployed, false otherwise
     */
    public function isModuleImageDeployed(): bool
    {
        return ModuleImageQuery::create()->filterByModuleId($this->getId())->count() > 0;
    }

    /**
     * @param ContainerInterface $container the Thelia container
     *
     * @return BaseModuleInterface a module instance
     *
     * @throws \InvalidArgumentException if the module could not be found in the container/
     */
    public function getModuleInstance(ContainerInterface $container): BaseModuleInterface
    {
        $instance = $container->get(\sprintf('module.%s', $this->getCode()));

        if (null === $instance) {
            throw new \InvalidArgumentException(\sprintf('Undefined module in container: "%s"', $this->getCode()));
        }

        return $instance;
    }

    /**
     * @param ContainerInterface $container the Thelia container
     *
     * @return BaseModuleInterface a module instance
     *
     * @throws \InvalidArgumentException if the module could not be found in the container/
     */
    public function getDeliveryModuleInstance(ContainerInterface $container): BaseModuleInterface
    {
        $instance = $this->getModuleInstance($container);

        if (
            !\in_array(DeliveryModuleInterface::class, class_implements($instance), true)
            && !\in_array(DeliveryModuleWithStateInterface::class, class_implements($instance), true)
        ) {
            throw new \InvalidArgumentException(\sprintf('Module "%s" is not a delivery module', $this->getCode()));
        }

        return $instance;
    }

    /**
     * @param ContainerInterface $container the Thelia container
     *
     * @return PaymentModuleInterface a payment module instance
     *
     * @throws \InvalidArgumentException if the module is not found or not a payment module
     */
    public function getPaymentModuleInstance(ContainerInterface $container): PaymentModuleInterface
    {
        $instance = $this->getModuleInstance($container);

        if (!$instance instanceof PaymentModuleInterface) {
            throw new \InvalidArgumentException(\sprintf('Module "%s" is not a payment module', $this->getCode()));
        }

        return $instance;
    }

    /**
     * @return \Thelia\Module\BaseModule a new module instance
     */
    public function createInstance(): \Thelia\Module\BaseModule
    {
        $moduleClass = new \ReflectionClass($this->getFullNamespace());

        return $moduleClass->newInstance();
    }

    /**
     * Calculate next position relative to module type.
     */
    protected function addCriteriaToPositionQuery(ModuleQuery $query): void
    {
        $query->filterByType($this->getType());
    }

    public function preInsert(?ConnectionInterface $con = null): true
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function getModuleDir(): string
    {
        return is_dir(THELIA_LOCAL_MODULE_DIR.$this->getBaseDir())
            ? THELIA_LOCAL_MODULE_DIR
            : THELIA_MODULE_DIR;
    }
}
