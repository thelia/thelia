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
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Company as CompanyResource;
use Thelia\Api\Security\AccountCaller;
use Thelia\Api\Service\Company\CompanyExceptionTranslator;
use Thelia\Domain\Company\Service\CompanyAccountWriter;
use Thelia\Model\CompanyQuery;

/**
 * A buyer who may edit the company changes its name, its identifiers and the address that receives
 * a copy of the order e-mails, and nothing else: what is not theirs to write (the parent company,
 * the note for the administrators, the billing address) is taken from the stored company, never from
 * the body. The SIRET and the VAT number stay as they are once an order of the company was left
 * without VAT, since that exemption was granted on them: the rules of {@see CompanyAccountWriter}.
 */
final readonly class CompanyFrontUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyAccountWriter $writer,
        private AccountCaller $caller,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CompanyResource
    {
        $customer = $this->caller->customer();

        if (null === $customer || !$data instanceof CompanyResource) {
            throw new AccessDeniedHttpException('Only a buyer of a company edits it.');
        }

        $company = CompanyExceptionTranslator::translating(fn () => $this->writer->updateCompany(
            $customer,
            (string) $data->getName(),
            $data->getSiret(),
            $data->getVatNumber(),
            $data->getNotificationEmail(),
        ));

        /** @var CompanyResource $resource */
        $resource = $this->transformer->modelToResource(CompanyResource::class, CompanyQuery::create()->findPk((int) $company->getId()) ?? $company, $context);

        return $resource;
    }
}
