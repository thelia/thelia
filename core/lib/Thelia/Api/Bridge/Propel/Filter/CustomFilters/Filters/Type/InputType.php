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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Type;

use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterTypeInterface;

class InputType implements TheliaFilterTypeInterface
{
    public static function getName(): string
    {
        return 'input';
    }
}
