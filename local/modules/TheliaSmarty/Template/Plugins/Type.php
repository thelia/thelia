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

namespace TheliaSmarty\Template\Plugins;

use Thelia\Core\Template\Smarty\Plugins\an;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class Type extends AbstractSmartyPlugin
{
    public function assertTypeModifier($value, $option)
    {
        $typeClass = "\\Thelia\\Type\\$option";
        if (!class_exists($typeClass)) {
            throw new \InvalidArgumentException(sprintf("Invalid type name `%s` in `assertType` modifier", $option));
        }

        $typeInstance = new $typeClass();
        if (!$typeInstance->isValid($value)) {
            return '';
        }

        return $value;
    }

    /**
     * Define the various smarty plugins handled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('modifier', 'assertType', $this, 'assertTypeModifier'),
        ];
    }
}
