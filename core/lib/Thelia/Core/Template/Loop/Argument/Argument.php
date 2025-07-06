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

namespace Thelia\Core\Template\Loop\Argument;

use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Argument
{
    public $type;
    public $mandatory;

    private $value;

    public function __construct(public $name, TypeCollection $type, public $default = null, $mandatory = false, public $empty = true, $value = null)
    {
        $this->type = $type;
        $this->mandatory = $mandatory ? true : false;

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

    public function setValue($value): void
    {
        if ($value === null) {
            $this->value = null;
        } else {
            if (false === $value) {
                /* (string) $value = "" */
                $this->value = 0;
            } elseif (\is_array($value)) {
                $this->value = implode(',', $value);
            } else {
                $this->value = (string) $value;
            }
        }
    }

    public static function createAnyTypeArgument($name, $default = null, $mandatory = false, $empty = true)
    {
        return new self(
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
        return new self(
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
        return new self(
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
        return new self(
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
        return new self(
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
        return new self(
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
     * @param null $default
     * @param bool $mandatory
     * @param bool $empty
     *
     * @return Argument
     *
     * @since 2.2
     */
    public static function createAnyListTypeArgument($name, $default = null, $mandatory = false, $empty = true)
    {
        return new self(
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
        return new self(
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
     * @param null $default
     * @param bool $mandatory
     * @param bool $empty
     *
     * @return Argument
     *
     * @since 2.4.0
     */
    public static function createAlphaNumStringTypeArgument($name, $default = null, $mandatory = false, $empty = true)
    {
        return new self(
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
     * @param null $default
     * @param bool $mandatory
     * @param bool $empty
     *
     * @return Argument
     *
     * @since 2.4.0
     */
    public static function createAlphaNumStringListTypeArgument($name, $default = null, $mandatory = false, $empty = true)
    {
        return new self(
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
