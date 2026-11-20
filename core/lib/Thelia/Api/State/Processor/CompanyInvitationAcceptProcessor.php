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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Thelia\Api\Security\AccountCaller;
use Thelia\Api\Service\Company\CompanyExceptionTranslator;
use Thelia\Api\Service\Company\JsonRequestBody;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Model\CompanyRole;

/**
 * The signed-in account takes the invitation it was sent: the service refuses a token that was
 * altered, expired or used, and one addressed to someone else; a guest is refused as well.
 */
final readonly class CompanyInvitationAcceptProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyInvitationService $invitations,
        private AccountCaller $caller,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $customer = $this->caller->customer() ?? throw new AccessDeniedHttpException('An account is needed to accept an invitation.');
        $token = JsonRequestBody::requiredString(JsonRequestBody::of($this->requestStack->getCurrentRequest()), 'token');

        $buyer = CompanyExceptionTranslator::translating(fn () => $this->invitations->accept($token, $customer));

        /** @var CompanyRole|null $role */
        $role = $buyer->getCompanyRole();

        return new JsonResponse([
            'companyId' => (int) $buyer->getCompanyId(),
            'roleCode' => (string) $role?->getCode(),
        ]);
    }
}
