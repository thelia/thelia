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

namespace Thelia\Domain\Catalog\Quantity;

use Thelia\Core\Translation\Translator;

/**
 * The unit of measure a combination is sold and priced in, stored in
 * `product_sale_elements.unit_code`. The value is the code kept in the database
 * and exchanged by the API; the symbol is what a page prints next to a quantity.
 */
enum UnitCode: string
{
    case PIECE = 'pc';
    case KILOGRAM = 'kg';
    case GRAM = 'g';
    case LITRE = 'l';
    case MILLILITRE = 'ml';
    case METRE = 'm';
    case CENTIMETRE = 'cm';
    case SQUARE_METRE = 'm2';
    case CUBIC_METRE = 'm3';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $unitCode): string => $unitCode->value, self::cases());
    }

    public function symbol(): string
    {
        return match ($this) {
            self::SQUARE_METRE => 'm²',
            self::CUBIC_METRE => 'm³',
            default => $this->value,
        };
    }

    /**
     * The name of the unit in the language asked for, or in the current one.
     */
    public function label(?string $locale = null): string
    {
        $name = match ($this) {
            self::PIECE => 'piece',
            self::KILOGRAM => 'kilogram',
            self::GRAM => 'gram',
            self::LITRE => 'litre',
            self::MILLILITRE => 'millilitre',
            self::METRE => 'metre',
            self::CENTIMETRE => 'centimetre',
            self::SQUARE_METRE => 'square metre',
            self::CUBIC_METRE => 'cubic metre',
        };

        try {
            return Translator::getInstance()->trans($name, [], null, $locale);
        } catch (\RuntimeException) {
            return $name;
        }
    }
}
