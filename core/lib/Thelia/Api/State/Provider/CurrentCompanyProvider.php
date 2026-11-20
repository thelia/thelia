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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Company;
use Thelia\Api\Security\AccountCaller;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Model\CompanyQuery;

/**
 * The company the caller belongs to, with no identifier in the address: what a buyer asks for is
 * their own, and the one they are not a buyer of reads as absent.
 */
final readonly class CurrentCompanyProvider implements ProviderInterface
{
    public function __construct(
        private AccountCaller $caller,
        private CompanyMembershipReader $membershipReader,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Company
    {
        $customer = $this->caller->customer();
        $membership = null === $customer ? null : $this->membershipReader->membershipOf($customer);
        $company = null === $membership ? null : CompanyQuery::create()->findPk($membership->companyId);

        if (null === $company) {
            return null;
        }

        $resource = $this->transformer->modelToResource(Company::class, $company, $context);

        return $resource instanceof Company ? $resource : null;
    }
}
