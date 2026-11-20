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
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\CompanyCustomer as CompanyCustomerResource;
use Thelia\Api\Security\AccountCaller;
use Thelia\Api\Service\Company\CompanyExceptionTranslator;
use Thelia\Domain\Company\Service\CompanyAccountWriter;

/**
 * A buyer who manages the buyers changes the role and the limits of one of their colleagues, never
 * beyond what their own role and limits hold, and never of a colleague whose role goes beyond
 * their own: the rules of {@see CompanyAccountWriter}. The colleague was read through the scope of
 * the company of the caller, so another company's buyer never gets here; the domain judges it again.
 */
final readonly class CompanyCustomerFrontUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyAccountWriter $writer,
        private AccountCaller $caller,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CompanyCustomerResource
    {
        $caller = $this->caller->customer() ?? throw new AccessDeniedHttpException('A buyer is expected.');

        if (!$data instanceof CompanyCustomerResource) {
            throw new NotFoundHttpException('Buyer not found.');
        }

        $changed = CompanyExceptionTranslator::translating(fn () => $this->writer->changeBuyer(
            $caller,
            (int) ($uriVariables['id'] ?? 0),
            $data->getRoleCode(),
            $data->getOrderLimit(),
            $data->getPeriodLimit(),
            $data->getPeriod(),
        ));

        /** @var CompanyCustomerResource $resource */
        $resource = $this->transformer->modelToResource(CompanyCustomerResource::class, $changed, $context);

        return $resource;
    }
}
