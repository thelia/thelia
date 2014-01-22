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
namespace Thelia\Core\Template\Loop\Argument;

use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class Argument
{
    public $name;
    public $type;
    public $default;
    public $mandatory;
    public $empty;

    private $value;

    public function __construct($name, \Thelia\Type\TypeCollection $type,  $default = null, $mandatory = false, $empty = true, $value = null)
    {
        $this->name         = $name;
        $this->type         = $type;
        $this->mandatory    = $mandatory ? true : false;
        $this->default      = $default;
        $this->empty        = $empty;

        $this->setValue($value);
    }

    public function getValue()
    {
        return $this->type->getFormattedValue($this->value);
    }

    public function setValue($value)
    {
        if ($value === null) {
            $this->value = null;
        } else {
            if (false === $value) {
                /* (string) $value = "" */
                $this->value = 0;
            } else {
                $this->value = (string) $value;
            }
        }
    }

    public static function createAnyTypeArgument($name, $default=null, $mandatory=false, $empty=true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\AnyType()
            ),
            $default,
            $mandatory,
            $empty
        );
    }

    public static function createIntTypeArgument($name, $default=null, $mandatory=false, $empty=true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\IntType()
            ),
            $default,
            $mandatory,
            $empty
        );
    }

    public static function createFloatTypeArgument($name, $default=null, $mandatory=false, $empty=true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\FloatType()
            ),
            $default,
            $mandatory,
            $empty
        );
    }

    public static function createBooleanTypeArgument($name, $default=null, $mandatory=false, $empty=true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\BooleanType()
            ),
            $default,
            $mandatory,
            $empty
        );
    }

    public static function createBooleanOrBothTypeArgument($name, $default=null, $mandatory=false, $empty=true)
    {
        return new Argument(
                $name,
                new TypeCollection(
                        new Type\BooleanOrBothType()
                ),
                $default,
                $mandatory,
                $empty
        );
    }

    public static function createIntListTypeArgument($name, $default=null, $mandatory=false, $empty=true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\IntListType()
            ),
            $default,
            $mandatory,
            $empty
        );
    }
}
