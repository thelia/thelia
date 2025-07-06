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

namespace Thelia\Type;

/**
 * Class AnyListType.
 *
 * @author GIlles Bourgeat <gbourgeat@openstudio.fr>
 */
class AnyListType extends BaseType
{
    public function getType(): string
    {
        return 'Any list type';
    }

    public function isValid($values): bool
    {
        return false === empty($values);
    }

    public function getFormattedValue($values)
    {
        return $this->isValid($values) ? explode(',', (string) $values) : null;
    }

    public function getFormOptions(): array
    {
        return [];
    }
}
