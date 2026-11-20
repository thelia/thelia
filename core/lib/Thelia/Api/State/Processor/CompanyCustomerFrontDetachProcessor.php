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
use Thelia\Api\Security\AccountCaller;
use Thelia\Api\Service\Company\CompanyExceptionTranslator;
use Thelia\Domain\Company\Service\CompanyAccountWriter;

/**
 * A buyer who manages the buyers detaches a colleague of their company, never one whose role holds
 * a permission theirs lacks, and never the last administrator: the rules of
 * {@see CompanyAccountWriter}.
 */
final readonly class CompanyCustomerFrontDetachProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyAccountWriter $writer,
        private AccountCaller $caller,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $caller = $this->caller->customer() ?? throw new AccessDeniedHttpException('A buyer is expected.');

        CompanyExceptionTranslator::translating(fn () => $this->writer->detachBuyer($caller, (int) ($uriVariables['id'] ?? 0)));

        return null;
    }
}
