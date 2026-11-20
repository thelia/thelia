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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\CompanyCustomer as CompanyCustomerResource;
use Thelia\Api\Service\Company\CompanyCustomerLookupLimiter;
use Thelia\Api\Service\Company\CompanyExceptionTranslator;
use Thelia\Domain\Company\CompanyFacade;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Attaches an account to a company with a role and limits, through the facade. The account is named
 * by its id or by its e-mail address; the lookup by address is rate limited per administrator, and an
 * address that holds no account reads as absent, like an id that matches none.
 */
final readonly class CompanyCustomerAdminCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyFacade $facade,
        private CompanyCustomerLookupLimiter $lookupLimiter,
        private Security $security,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CompanyCustomerResource
    {
        if (!$data instanceof CompanyCustomerResource || null === $data->getCompanyId() || null === $data->getRoleCode()) {
            throw new UnprocessableEntityHttpException('A company and a role are needed to attach an account.');
        }

        $company = CompanyQuery::create()->findPk($data->getCompanyId()) ?? throw new NotFoundHttpException('Company not found.');
        $customer = $this->customerOf($data);

        $buyer = CompanyExceptionTranslator::translating(fn () => $this->facade->attach(
            $company,
            $customer,
            $data->getRoleCode(),
            $data->getOrderLimit(),
            $data->getPeriodLimit(),
            $data->getPeriod(),
        ));

        /** @var CompanyCustomerResource $resource */
        $resource = $this->transformer->modelToResource(
            CompanyCustomerResource::class,
            CompanyCustomerQuery::create()->findPk($buyer->getId()) ?? $buyer,
            $context,
        );

        return $resource;
    }

    private function customerOf(CompanyCustomerResource $data): Customer
    {
        if (null !== $data->getCustomerId()) {
            return CustomerQuery::create()->findPk($data->getCustomerId()) ?? throw new NotFoundHttpException('Account not found.');
        }

        $email = trim((string) $data->getCustomerEmail());

        if ('' === $email) {
            throw new UnprocessableEntityHttpException('The account to attach is named by its id or by its e-mail address.');
        }

        $administrator = $this->security->getUser();

        if (!$this->lookupLimiter->allows(null === $administrator ? 'anonymous' : $administrator->getUserIdentifier())) {
            throw new TooManyRequestsHttpException(null, 'Too many lookups of an account by e-mail address: try again later.');
        }

        return CustomerQuery::create()->filterByEmail($email)->filterByIsGuest(0)->findOne() ?? throw new NotFoundHttpException('Account not found.');
    }
}
