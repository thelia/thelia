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
use Thelia\Model\CompanyQuery;

/**
 * Deletes a company that no order was placed for. One with history is a 409: it is suspended, not
 * deleted.
 */
final readonly class CompanyAdminDeleteProcessor implements ProcessorInterface
{
    public function __construct(private CompanyFacade $facade)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $company = CompanyQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('Company not found.');

        CompanyExceptionTranslator::translating(fn () => $this->facade->delete($company));

        return null;
    }
}
