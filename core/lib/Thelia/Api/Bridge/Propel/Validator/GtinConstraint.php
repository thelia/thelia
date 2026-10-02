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

namespace Thelia\Api\Bridge\Propel\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * The code is a GTIN of the GS1 family, or empty. A code that has not changed since it
 * was stored is let through, so a client that sends a combination back as it read it is
 * not refused for a code saved before the check existed.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class GtinConstraint extends Constraint
{
    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
