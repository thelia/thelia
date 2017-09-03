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

    public function __construct($name, \Thelia\Type\TypeCollection $type, $default = null, $mandatory = false, $empty = true, $value = null)
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

    public function getRawValue()
    {
        return $this->value;
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

    public static function createAnyTypeArgument($name, $default = null, $mandatory = false, $empty = true)
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

    public static function createIntTypeArgument($name, $default = null, $mandatory = false, $empty = true)
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

    public static function createFloatTypeArgument($name, $default = null, $mandatory = false, $empty = true)
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

    public static function createBooleanTypeArgument($name, $default = null, $mandatory = false, $empty = true)
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

    public static function createBooleanOrBothTypeArgument($name, $default = null, $mandatory = false, $empty = true)
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

    public static function createIntListTypeArgument($name, $default = null, $mandatory = false, $empty = true)
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

    /**
     * @param $name
     * @param null $default
     * @param bool $mandatory
     * @param bool $empty
     * @return Argument
     * @since 2.2
     */
    public static function createAnyListTypeArgument($name, $default = null, $mandatory = false, $empty = true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\AnyListType()
            ),
            $default,
            $mandatory,
            $empty
        );
    }

    public static function createEnumListTypeArgument($name, array $entries, $default = null, $mandatory = false, $empty = true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\EnumListType($entries)
            ),
            $default,
            $mandatory,
            $empty
        );
    }

    /**
     * @param $name
     * @param null $default
     * @param bool $mandatory
     * @param bool $empty
     * @return Argument
     * @since 2.4.0
     */
    public static function createAlphaNumStringTypeArgument($name, $default = null, $mandatory = false, $empty = true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\AlphaNumStringType()
            ),
            $default,
            $mandatory,
            $empty
        );
    }

    /**
     * @param $name
     * @param null $default
     * @param bool $mandatory
     * @param bool $empty
     * @return Argument
     * @since 2.4.0
     */
    public static function createAlphaNumStringListTypeArgument($name, $default = null, $mandatory = false, $empty = true)
    {
        return new Argument(
            $name,
            new TypeCollection(
                new Type\AlphaNumStringListType()
            ),
            $default,
            $mandatory,
            $empty
        );
    }
}
