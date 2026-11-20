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
use Thelia\Api\Service\Company\CompanyExceptionTranslator;
use Thelia\Domain\Company\CompanyFacade;
use Thelia\Model\CompanyCustomerQuery;

/**
 * Detaches a buyer from their company: the shared addresses they created go to the first
 * administrator who stays, and the last administrator cannot leave (409).
 */
final readonly class CompanyCustomerAdminDetachProcessor implements ProcessorInterface
{
    public function __construct(private CompanyFacade $facade)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $buyer = CompanyCustomerQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('Buyer not found.');
        $customer = $buyer->getCustomer() ?? throw new NotFoundHttpException('Buyer not found.');

        CompanyExceptionTranslator::translating(fn () => $this->facade->detach($customer));

        return null;
    }
}
