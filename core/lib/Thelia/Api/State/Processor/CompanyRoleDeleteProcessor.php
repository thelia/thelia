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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyRoleQuery;

/**
 * Deletes a role no buyer holds. The three roles the shop installs stay: the shop looks them up by
 * their code.
 */
final readonly class CompanyRoleDeleteProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $role = CompanyRoleQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('Role not found.');

        if (null === $role->getCompanyId() && \in_array($role->getCode(), [CompanyRoleCode::ADMINISTRATOR, CompanyRoleCode::BUYER, CompanyRoleCode::APPROVER], true)) {
            throw new ConflictHttpException('A role the shop installs cannot be deleted.');
        }

        if (CompanyCustomerQuery::create()->filterByCompanyRoleId((int) $role->getId())->exists()) {
            throw new ConflictHttpException('Buyers still hold this role.');
        }

        $role->delete();

        return null;
    }
}
