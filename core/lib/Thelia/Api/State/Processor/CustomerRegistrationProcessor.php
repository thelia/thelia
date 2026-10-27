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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Api\Resource\CustomerRegistrationInput;
use Thelia\Domain\Customer\CustomerFacade;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Exception\InvalidRegistrationTokenException;

/**
 * Registers an account through the same path as the storefront registration form: the e-mail
 * confirmation, the language and the group follow the shop's rules, not the generic persistence.
 *
 * The group is the default one, or the one the registration token opens; a token that opens none is a
 * violation on `registrationToken` (422), whether it never existed, was closed or was regenerated, and
 * nothing is created. The answer carries no confirmation token: it is mailed to the address, and an answer
 * that handed it to the caller would let anyone confirm an address they do not own.
 */
final readonly class CustomerRegistrationProcessor implements ProcessorInterface
{
    public function __construct(
        private CustomerFacade $customerFacade,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerResource
    {
        if (!$data instanceof CustomerRegistrationInput) {
            throw new \LogicException(\sprintf('Expected a %s, got %s.', CustomerRegistrationInput::class, get_debug_type($data)));
        }

        try {
            $customer = $this->customerFacade->register(
                new CustomerRegisterDTO(
                    firstname: $data->firstname,
                    lastname: $data->lastname,
                    email: $data->email,
                    password: $data->password,
                    langId: $data->langId,
                ),
                $data->registrationToken,
            );
        } catch (InvalidRegistrationTokenException $exception) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation($exception->getMessage(), null, [], $data, 'registrationToken', null)]), previous: $exception);
        }

        $resource = $this->transformer->modelToResource(
            resourceClass: CustomerResource::class,
            propelModel: $customer,
            context: $context,
        );

        if ($resource instanceof CustomerResource) {
            $resource->confirmationToken = null;
        }

        return $resource;
    }
}
