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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\CustomerPaymentMethod as CustomerPaymentMethodResource;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod as CustomerPaymentMethodModel;

/**
 * The only PATCH a customer may send a stored payment method is
 * `{"isDefault": true}`: the denormalization group of this operation carries
 * no other writable field, and this processor refuses a body that clears the
 * default or one that reached here empty.
 */
final readonly class CustomerPaymentMethodSetDefaultProcessor implements ProcessorInterface
{
    public function __construct(
        private CustomerPaymentMethodService $service,
        private TokenStorageInterface $tokenStorage,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof CustomerPaymentMethodResource || true !== $data->getIsDefault()) {
            throw new UnprocessableEntityHttpException('Only {"isDefault": true} is accepted.');
        }

        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer) {
            throw new AccessDeniedHttpException('A customer must be authenticated to change their default payment method.');
        }

        $model = $data->getPropelModel();

        if (!$model instanceof CustomerPaymentMethodModel) {
            throw new NotFoundHttpException('This stored payment method does not belong to this customer.');
        }

        $updated = $this->service->setDefault($customer, $model);

        return $this->transformer->modelToResource(
            resourceClass: CustomerPaymentMethodResource::class,
            propelModel: $updated,
            context: $context,
        );
    }
}
