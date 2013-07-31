<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Smarty;

/**
 * Class allowing to describe a smarty plugin
 *
 * Class SmartyPluginDescriptor
 * @package Thelia\Core\Template\Smarty
 */
class SmartyPluginDescriptor
{
    /**
     * @var string Smarty plugin type (block, function, etc.)
     */
    protected $type;

    /**
     * @var string Smarty plugin name. This name will be used in Smarty templates.
     */
    protected $name;

    /**
     * @var AbstractSmartyPlugin plugin implmentation class
     */
    protected $class;

    /**
     * @var string plugin implmentation method in $class
     */
    protected $method;

    public function __construct($type, $name, $class, $method)
    {
        $this->type = $type;
        $this->name = $name;
        $this->class = $class;
        $this->method = $method;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function getMethod()
    {
        return $this->method;
    }
}
