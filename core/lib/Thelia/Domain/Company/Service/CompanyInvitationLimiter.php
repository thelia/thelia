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

namespace Thelia\Domain\Company\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Caps the invitations sent in the name of a company: an invitation mails an address typed by
 * a buyer, so one buyer, or one caller, could otherwise make the shop mail anyone on demand.
 *
 * The window of the caller is spent first, so that a caller already over it stops eating the
 * budget of the company, which its other buyers need.
 */
final readonly class CompanyInvitationLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.company_invitation_per_company')]
        private RateLimiterFactoryInterface $perCompanyLimiter,
        #[Autowire(service: 'limiter.company_invitation_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Whether one more invitation may be sent for the company on behalf of this issuer, and spends it.
     */
    public function allows(int $companyId, ?int $issuerCustomerId): bool
    {
        // A caller is told apart by its address; without a request (a command), by the issuer when
        // there is one, and a command with no issuer is the merchant's own: only the company is capped.
        $client = $this->requestStack->getMainRequest()?->getClientIp()
            ?? (null === $issuerCustomerId ? null : 'customer:'.$issuerCustomerId);

        if (null !== $client && !$this->perClientLimiter->create($client)->consume()->isAccepted()) {
            return false;
        }

        return $this->perCompanyLimiter->create('company:'.$companyId)->consume()->isAccepted();
    }
}
