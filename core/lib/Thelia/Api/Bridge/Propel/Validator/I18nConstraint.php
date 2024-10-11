<?php

namespace Thelia\Api\Bridge\Propel\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class I18nConstraint extends Constraint
{
    public string $message = 'The title and locale must be defined at least once.';

    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
