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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\CompanyCustomer as CompanyCustomerResource;
use Thelia\Api\Service\Company\CompanyBuyerChanges;
use Thelia\Api\Service\Company\CompanyExceptionTranslator;
use Thelia\Model\CompanyCustomerQuery;

/**
 * Changes the role and the limits of a buyer, whoever they are, through the facade: the last
 * administrator of a company cannot lose the role (409).
 */
final readonly class CompanyCustomerAdminUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyBuyerChanges $changes,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CompanyCustomerResource
    {
        $buyer = CompanyCustomerQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('Buyer not found.');

        if (!$data instanceof CompanyCustomerResource) {
            throw new NotFoundHttpException('Buyer not found.');
        }

        $changed = CompanyExceptionTranslator::translating(fn () => $this->changes->apply($buyer, $data, null));

        /** @var CompanyCustomerResource $resource */
        $resource = $this->transformer->modelToResource(CompanyCustomerResource::class, $changed, $context);

        return $resource;
    }
}
