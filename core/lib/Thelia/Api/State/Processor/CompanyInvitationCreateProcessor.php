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
use Thelia\Api\Service\Company\CompanyInvitationMailer;
use Thelia\Api\Service\Company\JsonRequestBody;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Model\CompanyQuery;

/**
 * A buyer who manages the buyers invites an address to join their company. The token is mailed to
 * that address and is in no answer: whoever holds the mailbox holds the invitation. What the issuer
 * may give, and how many invitations may go out, is judged by the invitation service.
 */
final readonly class CompanyInvitationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private CompanyInvitationService $invitations,
        private CompanyInvitationMailer $mailer,
        private AccountCaller $caller,
        private CompanyMembershipReader $membershipReader,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $issuer = $this->caller->customer() ?? throw new AccessDeniedHttpException('A buyer is expected.');
        $membership = $this->membershipReader->membershipOf($issuer) ?? throw new AccessDeniedHttpException('Only a buyer of a company invites.');
        $company = CompanyQuery::create()->findPk($membership->companyId) ?? throw new AccessDeniedHttpException('Only a buyer of a company invites.');

        $body = JsonRequestBody::of($this->requestStack->getCurrentRequest());
        $email = JsonRequestBody::requiredString($body, 'email');
        $roleCode = JsonRequestBody::requiredString($body, 'roleCode');

        $token = CompanyExceptionTranslator::translating(fn (): string => $this->invitations->createToken(
            $company,
            $email,
            $roleCode,
            $issuer,
            JsonRequestBody::optionalString($body, 'orderLimit'),
            JsonRequestBody::optionalString($body, 'periodLimit'),
            JsonRequestBody::optionalString($body, 'period'),
        ));

        $this->mailer->send(
            mb_strtolower(trim($email)),
            $token,
            (string) $company->getName(),
            trim($issuer->getFirstname().' '.$issuer->getLastname()),
        );

        return new JsonResponse([
            'email' => mb_strtolower(trim($email)),
            'roleCode' => $roleCode,
            'expiresInSeconds' => CompanyInvitationService::LIFETIME_IN_SECONDS,
        ], 201);
    }
}
