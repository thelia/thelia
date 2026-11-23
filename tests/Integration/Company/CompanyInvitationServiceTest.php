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

namespace Thelia\Tests\Integration\Company;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\DTO\CompanyInvitation;
use Thelia\Domain\Company\Exception\CompanyGrantRefusedException;
use Thelia\Domain\Company\Exception\CompanyInvitationRefusedException;
use Thelia\Domain\Company\Exception\GuestCannotJoinCompanyException;
use Thelia\Domain\Company\Exception\InvalidCompanyDataException;
use Thelia\Domain\Company\Exception\InvalidCompanyInvitationException;
use Thelia\Domain\Company\Exception\UnknownCompanyRoleException;
use Thelia\Domain\Company\Service\CompanyGrantPolicy;
use Thelia\Domain\Company\Service\CompanyInvitationLimiter;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Domain\Company\Service\CompanyRoleResolver;
use Thelia\Model\Company;
use Thelia\Model\Customer;

/**
 * An invitation names a company, an address, a role and limits, and nothing else: whoever holds the
 * token can only take what it says, once, before it expires, and only the person it was sent to.
 */
final class CompanyInvitationServiceTest extends CompanyDomainTestCase
{
    private const string SECRET = 'a-secret-of-the-test-shop';

    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new MockClock('2026-11-18 10:00:00');
    }

    public function testAnInvitationIsReadAndAcceptedByTheAccountItWasSentTo(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('buyer@acme.test');

        $token = $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, $administrator, '500', '2000', 'month');

        $invitation = $this->service()->read($token);

        self::assertEquals(
            new CompanyInvitation(
                companyId: (int) $company->getId(),
                email: 'buyer@acme.test',
                roleCode: CompanyRoleCode::BUYER,
                orderLimit: '500',
                periodLimit: '2000',
                period: 'month',
                expiresAt: $this->clock->now()->getTimestamp() + 7 * 86400,
                issuerCustomerId: (int) $administrator->getId(),
            ),
            $invitation,
        );

        $buyer = $this->service()->accept($token, $invited);

        self::assertSame($company->getId(), $buyer->getCompanyId());
        $membership = $this->reader->membershipOf($invited);
        self::assertSame(CompanyRoleCode::BUYER, $membership->roleCode);
        self::assertSame('500.000000', $membership->orderLimit);
        self::assertSame('2000.000000', $membership->periodLimit);
        self::assertSame('month', $membership->period);
        self::assertCount(2, $this->events['action.company.customer.attach'] ?? [], 'The administrator, then the invited buyer.');
        self::assertSame($invited->getId(), $this->events['action.company.customer.attach'][1]->getBuyer()->getCustomerId());

        self::assertNull($this->service()->read($token), 'Once the address belongs to a buyer the invitation is spent.');

        try {
            $this->service()->accept($token, $invited);
            self::fail('The invitation was used twice.');
        } catch (InvalidCompanyInvitationException) {
        }
    }

    public function testTheMerchantInvitesAnyRoleWithAnyLimits(): void
    {
        $company = $this->company();
        $invited = $this->customer('boss@acme.test');

        $token = $this->service()->createToken($company, 'boss@acme.test', CompanyRoleCode::ADMINISTRATOR, null, null, null, null);

        self::assertNull($this->service()->read($token)?->issuerCustomerId);

        $this->service()->accept($token, $invited);

        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->reader->membershipOf($invited)->roleCode);
    }

    public function testTheAddressIsComparedWithoutRegardToCaseOrBlanks(): void
    {
        $company = $this->company();
        $invited = $this->customer('John.Smith@Acme.test');

        $token = $this->service()->createToken($company, '  JOHN.SMITH@acme.TEST ', CompanyRoleCode::BUYER, null, null, null, null);

        self::assertSame('john.smith@acme.test', $this->service()->read($token)->email);

        $this->service()->accept($token, $invited);

        self::assertNotNull($this->reader->membershipOf($invited));
    }

    public function testAnAlteredTokenIsNotValid(): void
    {
        $company = $this->company();
        $token = $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
        [$payload, $signature] = explode('.', $token);

        // Another company, another role, another address, a later date: each signed for something else.
        $forged = [
            'another payload, same signature' => $this->payload(['c' => (int) $company->getId() + 1]).'.'.$signature,
            'a better role' => $this->payload(['r' => CompanyRoleCode::ADMINISTRATOR]).'.'.$signature,
            'no limit' => $this->payload(['o' => null]).'.'.$signature,
            'another address' => $this->payload(['e' => 'other@acme.test']).'.'.$signature,
            'later' => $this->payload(['x' => $this->clock->now()->getTimestamp() + 365 * 86400]).'.'.$signature,
            'a character of the signature' => $payload.'.'.$this->flip($signature),
            'a character of the payload' => $this->flip($payload).'.'.$signature,
            'no signature' => $payload.'.',
            'no payload' => '.'.$signature,
            'one part' => $payload,
            'three parts' => $token.'.'.$signature,
            'empty' => '',
            'garbage' => 'not a token at all',
        ];

        foreach ($forged as $label => $candidate) {
            self::assertNull($this->service()->read($candidate), $label);
        }

        self::assertNotNull($this->service()->read($token));
    }

    public function testATokenSignedWithAnotherSecretOrAnotherKeyIsNotValid(): void
    {
        $company = $this->company();
        $token = $this->service(secret: 'the-secret-of-another-shop')->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, null, null, null, null);

        self::assertNull($this->service()->read($token));

        // Signed straight with the secret, without the key derived for the invitations.
        $payload = $this->payload(['c' => (int) $company->getId()]);
        $plain = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, self::SECRET, true)), '+/', '-_'), '=');

        self::assertNull($this->service()->read($payload.'.'.$plain));
    }

    public function testASignedTokenWhoseContentIsNotWhatAnInvitationHoldsIsNotValid(): void
    {
        $company = $this->company();
        $companyId = (int) $company->getId();

        self::assertNotNull($this->service()->read($this->signed(['c' => $companyId])), 'The control: signed the way the service does, and well formed.');

        foreach ([
            'company as text' => ['c' => (string) $companyId],
            'expiry as text' => ['c' => $companyId, 'x' => (string) ($this->clock->now()->getTimestamp() + 60)],
            'address as a number' => ['c' => $companyId, 'e' => 5],
            'role as a list' => ['c' => $companyId, 'r' => ['buyer']],
            'limit as a number' => ['c' => $companyId, 'o' => 500],
            'period as a number' => ['c' => $companyId, 'p' => 3],
            'issuer as text' => ['c' => $companyId, 'i' => 'someone'],
            'no company' => ['c' => null],
        ] as $label => $fields) {
            self::assertNull($this->service()->read($this->signed($fields)), $label);
        }
    }

    public function testAnInvitationExpiresAfterSevenDays(): void
    {
        $company = $this->company();
        $token = $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, null, null, null, null);

        $this->clock->sleep(7 * 86400 - 1);
        self::assertNotNull($this->service()->read($token), 'Still valid a second before.');

        $this->clock->sleep(1);
        self::assertNull($this->service()->read($token));

        try {
            $this->service()->accept($token, $this->customer('buyer@acme.test'));
            self::fail('An expired invitation was accepted.');
        } catch (InvalidCompanyInvitationException) {
        }
    }

    public function testAnInvitationToAnAddressThatAlreadyBelongsToABuyerIsRefusedAndSpent(): void
    {
        $company = $this->company();
        $other = $this->company('Globex');
        $member = $this->buyerOf($other, CompanyRoleCode::BUYER, $this->customer('taken@acme.test'));

        try {
            $this->service()->createToken($company, 'taken@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
            self::fail('The invitation was sent to a buyer of another company.');
        } catch (CompanyInvitationRefusedException) {
        }

        $token = $this->service()->createToken($company, 'later@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
        $this->buyerOf($other, CompanyRoleCode::BUYER, $this->customer('later@acme.test'));

        self::assertNull($this->service()->read($token), 'The address joined a company since.');
        self::assertNotNull($member);
    }

    public function testAnInvitationIsAcceptedOnlyByTheAccountItWasSentTo(): void
    {
        $company = $this->company();
        $someoneElse = $this->customer('someone.else@acme.test');
        $token = $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, null, null, null, null);

        try {
            $this->service()->accept($token, $someoneElse);
            self::fail('The invitation was taken by another account.');
        } catch (InvalidCompanyInvitationException $exception) {
            self::assertSame(InvalidCompanyInvitationException::addressedToSomeoneElse()->getMessage(), $exception->getMessage());
        }

        self::assertNull($this->buyerRow($someoneElse));
    }

    public function testAGuestWhoUsedTheAddressIsNeverAttached(): void
    {
        $company = $this->company();
        $guest = $this->guest();
        $guest->setEmail('guest@acme.test')->save($this->getPropelConnection());
        $token = $this->service()->createToken($company, 'guest@acme.test', CompanyRoleCode::BUYER, null, null, null, null);

        $this->expectException(GuestCannotJoinCompanyException::class);

        $this->service()->accept($token, $guest);
    }

    public function testACompanyThatNoLongerExistsOrARoleThatIsGoneMakesTheInvitationInvalid(): void
    {
        $company = $this->company();
        $role = $this->roleOf('acme_only', $company, [CompanyPermission::ORDER_PLACE]);
        $token = $this->service()->createToken($company, 'buyer@acme.test', 'acme_only', null, null, null, null);

        $role->delete($this->getPropelConnection());
        self::assertNull($this->service()->read($token), 'The role is gone.');

        $token = $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
        $this->facade->delete($company);

        self::assertNull($this->service()->read($token), 'The company is gone.');
    }

    public function testAnIssuerCannotGiveARoleAboveTheirOwn(): void
    {
        $company = $this->company();
        $this->roleOf('manager', $company, [CompanyPermission::ORDER_PLACE, CompanyPermission::ORDER_VIEW_OWN, CompanyPermission::BUYER_MANAGE]);
        $manager = $this->buyerOf($company, 'manager');

        foreach ([CompanyRoleCode::ADMINISTRATOR, CompanyRoleCode::APPROVER] as $above) {
            try {
                $this->service()->createToken($company, 'buyer@acme.test', $above, $manager, null, null, null);
                self::fail('The role '.$above.' was given by a manager.');
            } catch (CompanyGrantRefusedException $exception) {
                self::assertSame(CompanyGrantRefusedException::roleGoesBeyondTheIssuer()->getMessage(), $exception->getMessage());
            }
        }

        $token = $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, $manager, null, null, null);
        self::assertNotNull($this->service()->read($token), 'A role within their own is given.');
    }

    public function testAnIssuerCannotGiveLimitsAboveTheirOwn(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR, null, '500', '2000', 'month');

        foreach ([
            'order limit above' => ['500.01', '100', 'month'],
            'no order limit' => [null, '100', 'month'],
            'period limit above' => ['100', '2000.01', 'month'],
            'no period limit' => ['100', null, null],
            'another period' => ['100', '100', 'year'],
        ] as $label => [$orderLimit, $periodLimit, $period]) {
            try {
                $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, $administrator, $orderLimit, $periodLimit, $period);
                self::fail($label.' was accepted.');
            } catch (CompanyGrantRefusedException $exception) {
                self::assertSame(CompanyGrantRefusedException::limitsGoBeyondTheIssuer()->getMessage(), $exception->getMessage(), $label);
            }
        }

        $token = $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, $administrator, '500', '2000', 'month');
        self::assertNotNull($this->service()->read($token));
    }

    public function testOnlyABuyerWhoManagesBuyersOfThatCompanyInvites(): void
    {
        $company = $this->company('Acme');
        $other = $this->company('Globex');
        $buyer = $this->buyerOf($company);
        $administratorOfOther = $this->buyerOf($other, CompanyRoleCode::ADMINISTRATOR);
        $stranger = $this->customer();

        foreach ([$buyer, $administratorOfOther, $stranger] as $issuer) {
            try {
                $this->service()->createToken($company, 'new@acme.test', CompanyRoleCode::BUYER, $issuer, null, null, null);
                self::fail('Issuer '.$issuer->getId().' invited.');
            } catch (CompanyGrantRefusedException $exception) {
                self::assertSame(CompanyGrantRefusedException::issuerCannotManageBuyers()->getMessage(), $exception->getMessage());
            }
        }
    }

    public function testWhatCannotBeInvitedIsRefused(): void
    {
        $company = $this->company();

        try {
            $this->service()->createToken($company, 'buyer@acme.test', 'no_such_role', null, null, null, null);
            self::fail('An unknown role was invited.');
        } catch (UnknownCompanyRoleException) {
        }

        try {
            $this->service()->createToken($company, 'not-an-address', CompanyRoleCode::BUYER, null, null, null, null);
            self::fail('An invalid address was invited.');
        } catch (InvalidCompanyDataException) {
        }

        try {
            $this->service()->createToken($company, 'buyer@acme.test', CompanyRoleCode::BUYER, null, '-1', null, null);
            self::fail('A negative limit was invited.');
        } catch (InvalidCompanyDataException) {
        }

        $this->expectException(\Thelia\Domain\Company\Exception\UnknownCompanyException::class);
        $this->service()->createToken(new Company(), 'buyer@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
    }

    public function testTooManyInvitationsForOneCompanyAreRefused(): void
    {
        $company = $this->company();
        $service = $this->service(limiter: $this->limiter(perCompany: 2, perClient: 100));

        $service->createToken($company, 'one@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
        $service->createToken($company, 'two@acme.test', CompanyRoleCode::BUYER, null, null, null, null);

        $this->expectExceptionObject(CompanyInvitationRefusedException::tooManySent());
        $service->createToken($company, 'three@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
    }

    public function testTooManyInvitationsFromOneCallerAreRefusedWhateverTheCompany(): void
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');
        $service = $this->service(limiter: $this->limiter(perCompany: 100, perClient: 2));

        $service->createToken($first, 'one@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
        $service->createToken($second, 'two@acme.test', CompanyRoleCode::BUYER, null, null, null, null);

        $this->expectExceptionObject(CompanyInvitationRefusedException::tooManySent());
        $service->createToken($first, 'three@acme.test', CompanyRoleCode::BUYER, null, null, null, null);
    }

    public function testARefusedInvitationDoesNotSpendTheBudget(): void
    {
        $company = $this->company();
        $service = $this->service(limiter: $this->limiter(perCompany: 1, perClient: 1));

        try {
            $service->createToken($company, 'not-an-address', CompanyRoleCode::BUYER, null, null, null, null);
        } catch (InvalidCompanyDataException) {
        }

        self::assertNotNull($service->read($service->createToken($company, 'one@acme.test', CompanyRoleCode::BUYER, null, null, null, null)));
    }

    public function testTheShopDeclaresTwentyInvitationsAnHourPerCompanyAndTenPerCaller(): void
    {
        $container = static::getContainer();

        $perCompany = $container->get('limiter.company_invitation_per_company');
        $perClient = $container->get('limiter.company_invitation_per_client');
        \assert($perCompany instanceof RateLimiterFactoryInterface && $perClient instanceof RateLimiterFactoryInterface);

        self::assertSame(20, $perCompany->create('probe')->consume(0)->getLimit());
        self::assertSame(10, $perClient->create('probe')->consume(0)->getLimit());
    }

    public function testAnInvitationThatWasUsedStaysUsedWhenTheBuyerIsDetachedLater(): void
    {
        $company = $this->company();
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('leaver@acme.test');
        $token = $this->service()->createToken($company, 'leaver@acme.test', CompanyRoleCode::ADMINISTRATOR, $issuer, null, null, null);

        $this->service()->accept($token, $invited);
        $this->facade->detach($invited);

        self::assertNull($this->buyerRow($invited));
        self::assertNull($this->service()->read($token), 'A detached buyer cannot read the invitation they used.');

        try {
            $this->service()->accept($token, $invited);
            self::fail('The invitation came back to life when its buyer left.');
        } catch (InvalidCompanyInvitationException) {
        }

        self::assertNull($this->buyerRow($invited));
        self::assertSame(
            [[hash('sha256', $token), (int) $company->getId(), (int) $invited->getId()]],
            $this->usesOf($company),
            'The use is kept, with who used it and for which company.',
        );
    }

    public function testAnInvitationWhoseIssuerLeftTheCompanyIsNotAccepted(): void
    {
        $company = $this->company();
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('late@acme.test');
        $token = $this->service()->createToken($company, 'late@acme.test', CompanyRoleCode::BUYER, $issuer, null, null, null);

        $this->facade->detach($issuer);

        $this->assertNotAccepted($token, $invited, $company);
    }

    public function testAnInvitationWhoseIssuerWasDemotedBelowTheRoleItGivesIsNotAccepted(): void
    {
        $company = $this->company();
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('demoted@acme.test');
        $token = $this->service()->createToken($company, 'demoted@acme.test', CompanyRoleCode::ADMINISTRATOR, $issuer, null, null, null);

        $this->facade->changeRole($issuer, CompanyRoleCode::BUYER);

        $this->assertNotAccepted($token, $invited, $company);
    }

    public function testAnInvitationWhoseIssuerNoLongerManagesBuyersIsNotAccepted(): void
    {
        $company = $this->company();
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('nomanage@acme.test');
        $token = $this->service()->createToken($company, 'nomanage@acme.test', CompanyRoleCode::BUYER, $issuer, null, null, null);

        $this->facade->changeRole($issuer, CompanyRoleCode::BUYER);

        $this->assertNotAccepted($token, $invited, $company);
    }

    public function testAnInvitationWhoseIssuerWasGivenLowerLimitsIsNotAccepted(): void
    {
        $company = $this->company();
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR, null, '1000', null, null);
        $invited = $this->customer('capped@acme.test');
        $token = $this->service()->createToken($company, 'capped@acme.test', CompanyRoleCode::BUYER, $issuer, '800', null, null);

        $this->facade->changeLimits($issuer, '300', null, null);

        $this->assertNotAccepted($token, $invited, $company);
    }

    public function testAnInvitationWhoseIssuerIsADisabledAccountIsNotAccepted(): void
    {
        $company = $this->company();
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('disabled@acme.test');
        $token = $this->service()->createToken($company, 'disabled@acme.test', CompanyRoleCode::BUYER, $issuer, null, null, null);

        $issuer->setEnable(0)->save($this->getPropelConnection());

        $this->assertNotAccepted($token, $invited, $company);
    }

    public function testAnInvitationWhoseIssuerStillHoldsWhatItGivesIsAccepted(): void
    {
        $company = $this->company();
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('fine@acme.test');
        $token = $this->service()->createToken($company, 'fine@acme.test', CompanyRoleCode::BUYER, $issuer, null, null, null);

        $this->reader->membershipOf($issuer);

        self::assertSame($company->getId(), $this->service()->accept($token, $invited)->getCompanyId());
    }

    public function testTheKeyOfTheUseRefusesAnInvitationAnotherAcceptanceJustUsed(): void
    {
        $company = $this->company();
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('race@acme.test');
        $winner = $this->customer('winner@acme.test');
        $token = $this->service()->createToken($company, 'race@acme.test', CompanyRoleCode::BUYER, $issuer, null, null, null);
        $fingerprint = hash('sha256', $token);

        // The other acceptance commits its use when this one writes its own: both read the invitation as unused.
        $clock = $this->clockThatRuns(2, fn () => $this->getPropelConnection()->exec(\sprintf(
            "INSERT INTO `company_invitation_use` (`fingerprint`, `company_id`, `customer_id`, `addressee`, `used_at`) VALUES ('%s', %d, %d, '%s', NOW())",
            $fingerprint,
            (int) $company->getId(),
            (int) $winner->getId(),
            hash('sha256', 'race@acme.test'),
        )));

        try {
            $this->service($clock)->accept($token, $invited);
            self::fail('Two acceptances of the same invitation both went through.');
        } catch (InvalidCompanyInvitationException) {
        }

        self::assertNull($this->buyerRow($invited));
    }

    public function testTheDigestOfTheTokenIsWhatIsKeptNotTheTokenItself(): void
    {
        $company = $this->company();
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('digest@acme.test');
        $token = $this->service()->createToken($company, 'digest@acme.test', CompanyRoleCode::BUYER, $issuer, null, null, null);

        $this->service()->accept($token, $invited);

        $kept = $this->getPropelConnection()->query('SELECT `fingerprint` FROM `company_invitation_use`')->fetchAll(\PDO::FETCH_COLUMN);

        self::assertSame([hash('sha256', $token)], $kept);
        self::assertSame(64, \strlen($kept[0]));
    }

    private function assertNotAccepted(string $token, Customer $invited, Company $company): void
    {
        try {
            $this->service()->accept($token, $invited);
            self::fail('An invitation was accepted though its issuer no longer holds what it gives.');
        } catch (InvalidCompanyInvitationException $exception) {
            self::assertSame(InvalidCompanyInvitationException::notValid()->getMessage(), $exception->getMessage());
        }

        self::assertNull($this->buyerRow($invited));
        self::assertSame([], $this->usesOf($company), 'A refused acceptance uses nothing up.');
    }

    /**
     * @return list<array{string, int, int}>
     */
    private function usesOf(Company $company): array
    {
        $rows = $this->getPropelConnection()
            ->query('SELECT `fingerprint`, `company_id`, `customer_id` FROM `company_invitation_use` WHERE `company_id` = '.(int) $company->getId())
            ->fetchAll(\PDO::FETCH_NUM);

        return array_map(static fn (array $row): array => [(string) $row[0], (int) $row[1], (int) $row[2]], $rows);
    }

    /**
     * A clock that runs a hook when it is asked the time for the $call-th time: the way to put a
     * write between two steps of the service.
     */
    private function clockThatRuns(int $call, callable $hook): ClockInterface
    {
        return new class($this->clock, $call, $hook) implements ClockInterface {
            private int $asked = 0;

            public function __construct(private readonly ClockInterface $clock, private readonly int $call, private readonly mixed $hook)
            {
            }

            public function now(): \DateTimeImmutable
            {
                if (++$this->asked === $this->call) {
                    ($this->hook)();
                }

                return $this->clock->now();
            }
        };
    }

    private function service(?ClockInterface $clock = null, string $secret = self::SECRET, ?CompanyInvitationLimiter $limiter = null): CompanyInvitationService
    {
        $roleResolver = new CompanyRoleResolver();

        return new CompanyInvitationService(
            $this->facade,
            $roleResolver,
            new CompanyGrantPolicy($this->reader, $this->checker),
            $limiter ?? $this->limiter(perCompany: 1000, perClient: 1000),
            $clock ?? $this->clock,
            $secret,
            $this->reader,
        );
    }

    private function limiter(int $perCompany, int $perClient): CompanyInvitationLimiter
    {
        $window = static fn (int $limit): RateLimiterFactory => new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );

        return new CompanyInvitationLimiter($window($perCompany), $window($perClient), static::getContainer()->get('request_stack'));
    }

    /**
     * The payload of a token with some of its fields replaced, encoded as the service does.
     *
     * @param array<string, mixed> $replacements
     */
    private function payload(array $replacements): string
    {
        $fields = array_replace([
            'c' => 1,
            'e' => 'buyer@acme.test',
            'r' => CompanyRoleCode::BUYER,
            'o' => '100',
            'l' => null,
            'p' => null,
            'x' => $this->clock->now()->getTimestamp() + 86400,
            'i' => null,
        ], $replacements);

        return rtrim(strtr(base64_encode(json_encode($fields, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * A token as the service signs it, whatever its fields hold.
     *
     * @param array<string, mixed> $replacements
     */
    private function signed(array $replacements): string
    {
        $payload = $this->payload($replacements);
        $key = hash_hmac('sha256', 'thelia.company.invitation', self::SECRET, true);

        return $payload.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '=');
    }

    private function flip(string $value): string
    {
        $middle = intdiv(\strlen($value), 2);

        return substr($value, 0, $middle).('A' === $value[$middle] ? 'B' : 'A').substr($value, $middle + 1);
    }
}
