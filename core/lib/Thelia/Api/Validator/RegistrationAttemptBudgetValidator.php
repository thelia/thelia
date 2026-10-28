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

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Thelia\Api\Resource\CustomerRegistrationInput;
use Thelia\Domain\Customer\Service\CustomerRegistrationLimiter;

final class RegistrationAttemptBudgetValidator extends ConstraintValidator
{
    public function __construct(private readonly CustomerRegistrationLimiter $registrationLimiter)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RegistrationAttemptBudget) {
            throw new UnexpectedTypeException($constraint, RegistrationAttemptBudget::class);
        }

        if (!$value instanceof CustomerRegistrationInput) {
            throw new UnexpectedTypeException($value, CustomerRegistrationInput::class);
        }

        // An empty address names no account: nothing to probe, nothing to spend.
        if (null === $value->email || '' === trim($value->email)) {
            return;
        }

        $secondsToWait = $this->registrationLimiter->attempt($value->email);

        if (null !== $secondsToWait) {
            throw new TooManyRequestsHttpException($secondsToWait, 'Too many registration attempts from here. Try again later.');
        }
    }
}
