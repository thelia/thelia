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

namespace Thelia\Core\Template\Loop\Argument;

use Thelia\Type\AlphaNumStringListType;
use Thelia\Type\AlphaNumStringType;
use Thelia\Type\AnyListType;
use Thelia\Type\AnyType;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\BooleanType;
use Thelia\Type\EnumListType;
use Thelia\Type\FloatType;
use Thelia\Type\IntListType;
use Thelia\Type\IntType;
use Thelia\Type\TypeCollection;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Argument
{
    public bool $mandatory;
    private int|string|null $value = null;

    public function __construct(public $name, public TypeCollection $type, public $default = null, $mandatory = false, public $empty = true, $value = null)
    {
        $this->mandatory = (bool) $mandatory;

        $this->setValue($value);
    }

    public function getValue()
    {
        return $this->type->getFormattedValue($this->value);
    }

    public function getRawValue(): int|string|null
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        if (null === $value) {
            $this->value = null;
        } elseif (false === $value) {
            /* (string) $value = "" */
            $this->value = 0;
        } elseif (\is_array($value)) {
            $this->value = implode(',', $value);
        } else {
            $this->value = (string) $value;
        }
    }

    public static function createAnyTypeArgument($name, $default = null, $mandatory = false, $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new AnyType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createIntTypeArgument($name, $default = null, $mandatory = false, $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new IntType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createFloatTypeArgument($name, $default = null, $mandatory = false, $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new FloatType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createBooleanTypeArgument($name, $default = null, $mandatory = false, $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new BooleanType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createBooleanOrBothTypeArgument($name, $default = null, $mandatory = false, $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new BooleanOrBothType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createIntListTypeArgument($name, $default = null, $mandatory = false, $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new IntListType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createAnyListTypeArgument($name, $default = null, bool $mandatory = false, bool $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new AnyListType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createEnumListTypeArgument($name, array $entries, $default = null, $mandatory = false, $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new EnumListType($entries),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createAlphaNumStringTypeArgument($name, $default = null, bool $mandatory = false, bool $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new AlphaNumStringType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }

    public static function createAlphaNumStringListTypeArgument($name, $default = null, bool $mandatory = false, bool $empty = true): self
    {
        return new self(
            $name,
            new TypeCollection(
                new AlphaNumStringListType(),
            ),
            $default,
            $mandatory,
            $empty,
        );
    }
}
