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

namespace Thelia\Api\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Spends one registration attempt of the caller, on the address the body carries, and
 * answers 429 when the budget is gone.
 *
 * It must be declared before every constraint that looks the address up (it is a class
 * constraint, and those run in declaration order), so that a caller over budget learns
 * nothing about the address.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class RegistrationAttemptBudget extends Constraint
{
    public function validatedBy(): string
    {
        return static::class.'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
