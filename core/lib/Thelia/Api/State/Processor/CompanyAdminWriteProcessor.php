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
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Company as CompanyResource;
use Thelia\Api\Service\Company\CompanyExceptionTranslator;
use Thelia\Domain\Company\CompanyFacade;
use Thelia\Domain\Company\DTO\CompanyData;
use Thelia\Model\Company;
use Thelia\Model\CompanyQuery;

/**
 * Writes a company through the facade the back office uses, so that the identifiers are copied onto
 * the billing address and a refusal reads the same on both sides. A company is written in full: what
 * a PUT leaves out is emptied, what a PATCH leaves out keeps its value. `active` suspends or
 * reactivates the company when it differs from the stored one, and is ignored at creation.
 */
final readonly class CompanyAdminWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyFacade $facade,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CompanyResource
    {
        if (!$data instanceof CompanyResource) {
            throw new UnprocessableEntityHttpException('A company is expected.');
        }

        $company = CompanyExceptionTranslator::translating(function () use ($data, $operation, $uriVariables): Company {
            $companyData = new CompanyData(
                name: (string) $data->getName(),
                siret: $data->getSiret(),
                vatNumber: $data->getVatNumber(),
                parentId: $data->getParentId(),
                notificationEmail: $data->getNotificationEmail(),
                internalNote: $data->getInternalNote(),
                billingAddressId: $data->getBillingAddressId(),
            );

            if ($operation instanceof Post) {
                return $this->facade->create($companyData);
            }

            $company = CompanyQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('Company not found.');
            $wasActive = 1 === (int) $company->getActive();

            $this->facade->update($company, $companyData);

            if (null !== $data->getActive() && $data->getActive() !== $wasActive) {
                $data->getActive() ? $this->facade->reactivate($company) : $this->facade->suspend($company);
            }

            return $company;
        });

        /** @var CompanyResource $resource */
        $resource = $this->transformer->modelToResource(CompanyResource::class, CompanyQuery::create()->findPk($company->getId()) ?? $company, $context);

        return $resource;
    }
}
