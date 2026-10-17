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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Resource\CustomerPaymentMethod as CustomerPaymentMethodResource;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod as CustomerPaymentMethodModel;

/**
 * Drops a stored payment method through CustomerPaymentMethodService::forget(),
 * never through the generic Propel remove processor: the provider is asked
 * first, and a refusal (StoredMethodRefusedException, mapped to 409) keeps the
 * row.
 *
 * CustomerPaymentMethodOwnershipExtension already scoped the read step of this
 * operation to the authenticated customer, so a foreign id never reaches here;
 * the checks below are the same rule applied again rather than relied upon.
 */
final readonly class CustomerPaymentMethodForgetProcessor implements ProcessorInterface
{
    public function __construct(
        private CustomerPaymentMethodService $service,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer) {
            throw new AccessDeniedHttpException('A customer must be authenticated to forget a stored payment method.');
        }

        if (!$data instanceof CustomerPaymentMethodResource) {
            return;
        }

        $model = $data->getPropelModel();

        if (!$model instanceof CustomerPaymentMethodModel) {
            throw new NotFoundHttpException('This stored payment method does not belong to this customer.');
        }

        $this->service->forget($customer, $model);
    }
}
