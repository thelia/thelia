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

namespace Thelia\Core\Security\Resource;

use Thelia\Core\Security\Exception\ResourceException;

/**
 * This class contains all Thelia admin resources.
 *
 * @author Etienne roudeix <eroudeix@openstudio.fr>
 */
class AdminResources
{
    /**
     * @deprecated 2.3
     * @removed 2.5
     */
    private static $selfReflection = null;

    /**
     * @deprecated 2.3
     * @removed 2.5
     *
     * @param $name
     *
     * @return string the constant value
     */
    public static function retrieve($name)
    {
        $constantName = strtoupper($name);
        if (null === self::$selfReflection) {
            self::$selfReflection = new \ReflectionClass(__CLASS__);
        }
        if (self::$selfReflection->hasConstant($constantName)) {
            return self::$selfReflection->getConstant($constantName);
        }
        throw new ResourceException(sprintf('Resource `%s` not found', $constantName), ResourceException::RESOURCE_NOT_FOUND);
    }

    public const SUPERADMINISTRATOR = 'SUPERADMINISTRATOR';

    public const ADDRESS = 'admin.address';

    public const ADMINISTRATOR = 'admin.configuration.administrator';

    public const ADVANCED_CONFIGURATION = 'admin.configuration.advanced';

    public const AREA = 'admin.configuration.area';

    public const ATTRIBUTE = 'admin.configuration.attribute';

    public const BRAND = 'admin.brand';

    public const CATEGORY = 'admin.category';

    public const CONFIG = 'admin.configuration';

    public const CONTENT = 'admin.content';

    public const COUNTRY = 'admin.configuration.country';

    public const STATE = 'admin.configuration.state';

    public const COUPON = 'admin.coupon';

    public const CURRENCY = 'admin.configuration.currency';

    public const CUSTOMER = 'admin.customer';

    public const FEATURE = 'admin.configuration.feature';

    public const FOLDER = 'admin.folder';

    public const HOME = 'admin.home';

    public const LANGUAGE = 'admin.configuration.language';

    public const MAILING_SYSTEM = 'admin.configuration.mailing-system';

    public const MESSAGE = 'admin.configuration.message';

    public const MODULE = 'admin.module';

    public const HOOK = 'admin.hook';

    public const MODULE_HOOK = 'admin.module-hook';

    public const ORDER = 'admin.order';

    public const ORDER_STATUS = 'admin.configuration.order-status';

    public const PRODUCT = 'admin.product';

    public const PROFILE = 'admin.configuration.profile';

    public const SHIPPING_ZONE = 'admin.configuration.shipping-zone';

    public const TAX = 'admin.configuration.tax';

    public const TEMPLATE = 'admin.configuration.template';

    public const SYSTEM_LOG = 'admin.configuration.system-logs';

    public const ADMIN_LOG = 'admin.configuration.admin-logs';

    public const STORE = 'admin.configuration.store';

    public const TRANSLATIONS = 'admin.configuration.translations';

    public const UPDATE = 'admin.configuration.update';

    public const EXPORT = 'admin.export';

    public const IMPORT = 'admin.import';

    public const TOOLS = 'admin.tools';

    public const SALES = 'admin.sales';

    public const API = 'admin.configuration.api';

    public const TITLE = 'admin.customer.title';

    /**
     * Stock all resources by modules
     * Exemple :
     * [
     *      "thelia" => [
     *          "ADDRESS" => "admin.address",
     *          ...
     *      ],
     *      "Front" => [
     *          ...
     *      ]
     * ].
     *
     * @var array $resources
     */
    protected $resources;

    /**
     * Create a new AdminRessources instance.
     *
     * @param array $resources with format module => [ KEY => value ]
     */
    public function __construct($resources)
    {
        $this->resources = $resources;
    }

    /**
     * @param string $name
     * @param string $module
     *
     * @return string
     */
    public function getResource($name, $module = 'thelia')
    {
        $constantName = strtoupper($name);

        if (isset($this->resources[$module])) {
            if (isset($this->resources[$module][$constantName])) {
                return $this->resources[$module][$constantName];
            }
            throw new ResourceException(sprintf('Resource `%s` not found', $module),
                    ResourceException::RESOURCE_NOT_FOUND);
        } else {
            throw new ResourceException(sprintf('Module `%s` not found', $module),
                ResourceException::RESOURCE_NOT_FOUND);
        }
    }

    /**
     * @param $data with format
     * [
     *     "ADDRESS" => "admin.address",
     *     ...
     * ]
     * @param $module string ModuleCode
     *
     * @throws \Exception
     */
    public function addModuleResources($data, $module = 'thelia')
    {
        if (null !== $data && \is_array($data)) {
            $this->resources[$module] = $data;
        } else {
            throw new \Exception('Format pass to addModuleResources method is not valid');
        }
    }

    /**
     * @param string $name
     * @param string $value
     * @param string $module
     */
    public function addResource($name, $value, $module = 'thelia')
    {
        if (null !== $name && null !== $value) {
            $nameFormated = strtoupper($name);
            if (!$this->resources[$module]) {
                $this->resources[$module] = [];
            }
            $this->resources[$module][$nameFormated] = $value;
        }
    }
}
