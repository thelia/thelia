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

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Validator\RegistrationAttemptBudget;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\LangQuery;

/**
 * The body of `POST /api/front/customers`: the visitor registers an account.
 *
 * It is a body of its own rather than the front write group of {@see Customer}, which two other
 * operations share: an e-mail address and a password are asked here and nowhere else, and the
 * group is not a field at all. A visitor who wants to join a group sends the token of its
 * registration link; the server resolves the token and the visitor never names a group.
 *
 * Every field is write only: the answer is the customer, not this body.
 */
#[RegistrationAttemptBudget(groups: [Customer::GROUP_FRONT_REGISTER])]
class CustomerRegistrationInput
{
    #[Groups([Customer::GROUP_FRONT_REGISTER])]
    #[NotBlank(groups: [Customer::GROUP_FRONT_REGISTER])]
    public ?string $firstname = null;

    #[Groups([Customer::GROUP_FRONT_REGISTER])]
    #[NotBlank(groups: [Customer::GROUP_FRONT_REGISTER])]
    public ?string $lastname = null;

    #[Groups([Customer::GROUP_FRONT_REGISTER])]
    #[NotBlank(groups: [Customer::GROUP_FRONT_REGISTER])]
    #[Email(groups: [Customer::GROUP_FRONT_REGISTER])]
    public ?string $email = null;

    #[Groups([Customer::GROUP_FRONT_REGISTER])]
    #[NotBlank(groups: [Customer::GROUP_FRONT_REGISTER])]
    public ?string $password = null;

    #[Groups([Customer::GROUP_FRONT_REGISTER])]
    public ?int $langId = null;

    /**
     * The token of a registration link. Left out, the account joins the default group; one that opens
     * no group is refused, and nothing is created.
     */
    #[Groups([Customer::GROUP_FRONT_REGISTER])]
    #[Length(max: 255, groups: [Customer::GROUP_FRONT_REGISTER])]
    public ?string $registrationToken = null;

    /**
     * The same rule as the registration form: an address that belongs to an account is refused. The
     * record a guest checkout left on an address is not an account.
     */
    #[Callback(groups: [Customer::GROUP_FRONT_REGISTER])]
    public function validateEmailIsFree(ExecutionContextInterface $context): void
    {
        if (null !== $this->email && '' !== $this->email && null !== CustomerQuery::getCustomerByEmail($this->email)) {
            $context->buildViolation('This email already exists.')
                ->atPath('email')
                ->addViolation();
        }
    }

    #[Callback(groups: [Customer::GROUP_FRONT_REGISTER])]
    public function validatePasswordLength(ExecutionContextInterface $context): void
    {
        $minimum = (int) ConfigQuery::read('password.length', 4);

        if (null !== $this->password && '' !== $this->password && mb_strlen($this->password) < $minimum) {
            $context->buildViolation('This value is too short. It should have {{ limit }} characters or more.')
                ->setParameter('{{ limit }}', (string) $minimum)
                ->atPath('password')
                ->addViolation();
        }
    }

    #[Callback(groups: [Customer::GROUP_FRONT_REGISTER])]
    public function validateLanguageExists(ExecutionContextInterface $context): void
    {
        if (null !== $this->langId && !LangQuery::create()->filterById($this->langId)->exists()) {
            $context->buildViolation('This language does not exist.')
                ->atPath('langId')
                ->addViolation();
        }
    }
}
