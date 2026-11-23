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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Domain\Company\CompanyFacade;
use Thelia\Domain\Company\DTO\CompanyInvitation;
use Thelia\Domain\Company\Exception\CompanyGrantRefusedException;
use Thelia\Domain\Company\Exception\CompanyInvitationRefusedException;
use Thelia\Domain\Company\Exception\InvalidCompanyDataException;
use Thelia\Domain\Company\Exception\InvalidCompanyInvitationException;
use Thelia\Domain\Company\Exception\UnknownCompanyException;
use Thelia\Domain\Company\Exception\UnknownCompanyRoleException;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomer;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyInvitationUse;
use Thelia\Model\CompanyInvitationUseQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Invites someone to join a company, and takes them in when they come back with the invitation.
 *
 * The invitation is a token that is signed rather than stored: it names the company, the e-mail
 * address it was sent to, the role and the limits the new buyer will hold, the moment it stops
 * being accepted, and who issued it. Nothing is written when it is created. It is used once:
 * accepting it records the digest of the token in the same transaction as the link, and an
 * invitation whose digest is recorded is never accepted again, whatever became of the buyer it
 * took in. It is only a token: mailing it is up to the caller.
 *
 * Who invites cannot raise the invited above themselves (see {@see CompanyGrantPolicy}), and
 * must still be able to when the invitation is accepted: still a member of the company, still
 * managing its buyers, still holding the role and the limits they gave. The merchant, who has no
 * issuer, is not bound.
 */
final readonly class CompanyInvitationService
{
    /** An invitation is answered when the person has read it, which takes days, not minutes. */
    public const int LIFETIME_IN_SECONDS = 604800;

    private const string SIGNATURE_ALGORITHM = 'sha256';

    public function __construct(
        private CompanyFacade $facade,
        private CompanyRoleResolver $roleResolver,
        private CompanyGrantPolicy $grantPolicy,
        private CompanyInvitationLimiter $limiter,
        private ClockInterface $clock,
        #[Autowire(param: 'kernel.secret')]
        private string $applicationSecret,
        private CompanyMembershipReader $membershipReader,
    ) {
    }

    /**
     * @throws CompanyInvitationRefusedException when the address already belongs to a buyer, or too many invitations went out
     * @throws CompanyGrantRefusedException      when the issuer may not give this role and these limits
     * @throws UnknownCompanyRoleException
     * @throws InvalidCompanyDataException
     */
    public function createToken(
        Company $company,
        string $email,
        string $roleCode,
        ?Customer $issuer,
        ?string $orderLimit,
        ?string $periodLimit,
        ?string $period,
    ): string {
        $email = $this->normalize($email);
        $companyId = (int) $company->getId();

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidCompanyDataException('The e-mail address to invite is not valid.');
        }

        CompanyLimits::assertValid($orderLimit, $periodLimit, $period);

        if (0 === $companyId || !CompanyQuery::create()->filterById($companyId)->exists()) {
            throw UnknownCompanyException::withId($companyId);
        }

        $role = $this->roleResolver->resolve($companyId, $roleCode)
            ?? throw UnknownCompanyRoleException::forCompany($roleCode, $companyId);

        if (null !== $issuer) {
            $this->grantPolicy->assertIssuerMayGrant($issuer, $companyId, $role, $orderLimit, $periodLimit, $period);
        }

        if ($this->belongsToABuyer($email)) {
            throw CompanyInvitationRefusedException::alreadyInACompany();
        }

        $issuerId = null === $issuer ? null : (int) $issuer->getId();

        if (!$this->limiter->allows($companyId, $issuerId)) {
            throw CompanyInvitationRefusedException::tooManySent();
        }

        $payload = $this->encode(json_encode([
            'c' => $companyId,
            'e' => $email,
            'r' => $roleCode,
            'o' => $orderLimit,
            'l' => $periodLimit,
            'p' => $period,
            'x' => $this->clock->now()->getTimestamp() + self::LIFETIME_IN_SECONDS,
            'i' => $issuerId,
        ], \JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->encode($this->sign($payload));
    }

    /**
     * What the invitation says, or null when the token is not one this shop issued, was altered,
     * has expired, or can no longer be used: the company or the role is gone, the address
     * already belongs to a buyer, or the invitation was used.
     */
    public function read(string $token): ?CompanyInvitation
    {
        $parts = explode('.', $token);

        if (2 !== \count($parts)) {
            return null;
        }

        [$payload, $signature] = $parts;

        // Always compared, whatever the payload holds, so that a token that decodes to nothing and
        // one with a wrong signature take the same path.
        if (!hash_equals($this->encode($this->sign($payload)), $signature)) {
            return null;
        }

        $invitation = $this->decode($payload);
        $now = $this->clock->now();

        if (null === $invitation || $invitation->expiresAt <= $now->getTimestamp()) {
            return null;
        }

        if (!CompanyQuery::create()->filterById($invitation->companyId)->exists()
            || null === $this->roleResolver->resolve($invitation->companyId, $invitation->roleCode)
            || $this->belongsToABuyer($invitation->email)
            || CompanyInvitationUseQuery::create()->filterByFingerprint($this->fingerprint($token))->exists()
            || $this->addresseeJoinedSinceIssued($invitation, $now)) {
            return null;
        }

        return $invitation;
    }

    /**
     * Makes the customer a buyer of the company the invitation names, with its role and limits.
     *
     * @throws InvalidCompanyInvitationException                                  when the invitation is not valid, was used, was sent to another address,
     *                                                                            or its issuer no longer holds what it gives
     * @throws \Thelia\Domain\Company\Exception\GuestCannotJoinCompanyException
     * @throws \Thelia\Domain\Company\Exception\CustomerAlreadyInCompanyException
     */
    public function accept(string $token, Customer $customer): CompanyCustomer
    {
        $invitation = $this->read($token) ?? throw InvalidCompanyInvitationException::notValid();

        if ($this->normalize((string) $customer->getEmail()) !== $invitation->email) {
            throw InvalidCompanyInvitationException::addressedToSomeoneElse();
        }

        $company = CompanyQuery::create()->findPk($invitation->companyId) ?? throw InvalidCompanyInvitationException::notValid();

        $fingerprint = $this->fingerprint($token);

        return $this->facade->attach(
            $company,
            $customer,
            $invitation->roleCode,
            $invitation->orderLimit,
            $invitation->periodLimit,
            $invitation->period,
            function (ConnectionInterface $connection) use ($invitation, $fingerprint, $customer): void {
                $this->assertTheIssuerStillMayGive($invitation, $connection);
                $this->recordTheUse($fingerprint, $invitation, (int) $customer->getId(), $connection);
            },
        );
    }

    /**
     * Run under the lock of the company, so that nobody leaves or changes role while it is judged.
     */
    private function assertTheIssuerStillMayGive(CompanyInvitation $invitation, ConnectionInterface $connection): void
    {
        $issuerId = $invitation->issuerCustomerId;

        if (null === $issuerId) {
            return;
        }

        $role = $this->roleResolver->resolve($invitation->companyId, $invitation->roleCode, $connection)
            ?? throw InvalidCompanyInvitationException::notValid();

        // What this request read of the issuer before the lock is read again.
        $this->membershipReader->forget($issuerId);

        if (!CustomerQuery::create()->filterById($issuerId)->filterByEnable(1)->exists($connection)) {
            throw InvalidCompanyInvitationException::notValid();
        }

        try {
            $this->grantPolicy->assertIssuerMayGrant(
                $issuerId,
                $invitation->companyId,
                $role,
                $invitation->orderLimit,
                $invitation->periodLimit,
                $invitation->period,
            );
        } catch (CompanyGrantRefusedException) {
            throw InvalidCompanyInvitationException::notValid();
        }
    }

    /**
     * The key of the digest is what lets one acceptance through when two arrive together.
     */
    private function recordTheUse(string $fingerprint, CompanyInvitation $invitation, int $customerId, ConnectionInterface $connection): void
    {
        $use = new CompanyInvitationUse();
        $use->setFingerprint($fingerprint);
        $use->setCompanyId($invitation->companyId);
        $use->setCustomerId($customerId);
        $use->setAddressee(CompanyInvitationAddressee::digest($invitation->email));
        $use->setUsedAt($this->clock->now()->format('Y-m-d H:i:s'));

        try {
            $use->save($connection);
        } catch (PropelException $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof \PDOException && 1062 === ($previous->errorInfo[1] ?? null)) {
                throw InvalidCompanyInvitationException::notValid();
            }

            throw $exception;
        }
    }

    private function fingerprint(string $token): string
    {
        return hash('sha256', $token);
    }

    private function normalize(string $email): string
    {
        return CompanyInvitationAddressee::normalize($email);
    }

    /**
     * An invitation issued before its addressee last joined the company through another one is
     * spent with it: once detached, they do not come back with an invitation sent before. The
     * earlier join is found by the address it was sent to, not by the one its account holds now.
     */
    private function addresseeJoinedSinceIssued(CompanyInvitation $invitation, \DateTimeImmutable $now): bool
    {
        // Written in the time zone the uses are recorded in.
        $issuedAt = $now->setTimestamp($invitation->expiresAt - self::LIFETIME_IN_SECONDS)->format('Y-m-d H:i:s');

        return CompanyInvitationUseQuery::create()
            ->filterByCompanyId($invitation->companyId)
            ->filterByAddressee(CompanyInvitationAddressee::digest($invitation->email))
            ->filterByUsedAt($issuedAt, Criteria::GREATER_EQUAL)
            ->exists();
    }

    private function belongsToABuyer(string $email): bool
    {
        return CompanyCustomerQuery::create()
            ->useCustomerQuery()
                ->filterByEmail($email)
            ->endUse()
            ->exists();
    }

    private function decode(string $payload): ?CompanyInvitation
    {
        $json = base64_decode(strtr($payload, '-_', '+/'), true);

        if (false === $json) {
            return null;
        }

        try {
            $data = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data)
            || !\is_int($data['c'] ?? null) || !\is_string($data['e'] ?? null) || !\is_string($data['r'] ?? null)
            || !\is_int($data['x'] ?? null) || !(null === ($data['i'] ?? null) || \is_int($data['i']))
            || !(null === ($data['o'] ?? null) || \is_string($data['o']))
            || !(null === ($data['l'] ?? null) || \is_string($data['l']))
            || !(null === ($data['p'] ?? null) || \is_string($data['p']))) {
            return null;
        }

        return new CompanyInvitation(
            companyId: $data['c'],
            email: $data['e'],
            roleCode: $data['r'],
            orderLimit: $data['o'] ?? null,
            periodLimit: $data['l'] ?? null,
            period: $data['p'] ?? null,
            expiresAt: $data['x'],
            issuerCustomerId: $data['i'] ?? null,
        );
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function sign(string $payload): string
    {
        $key = hash_hmac(self::SIGNATURE_ALGORITHM, 'thelia.company.invitation', $this->applicationSecret, true);

        return hash_hmac(self::SIGNATURE_ALGORITHM, $payload, $key, true);
    }
}
