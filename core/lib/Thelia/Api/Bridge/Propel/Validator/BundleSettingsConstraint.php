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
 * The bundle settings of a product written through the API: judged by the same service as
 * the events of the catalogue.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class BundleSettingsConstraint extends Constraint
{
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
