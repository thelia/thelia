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

namespace Thelia\Domain\Search\Dictionary;

final class SearchRedirectTargetType
{
    public const string PRODUCT = 'product';
    public const string CATEGORY = 'category';
    public const string CONTENT = 'content';
    public const string FOLDER = 'folder';
    public const string BRAND = 'brand';
    public const string URL = 'url';

    public const array ENTITIES = [self::PRODUCT, self::CATEGORY, self::CONTENT, self::FOLDER, self::BRAND];

    public const array ALL = [...self::ENTITIES, self::URL];

    public static function isEntity(string $type): bool
    {
        return \in_array($type, self::ENTITIES, true);
    }
}
