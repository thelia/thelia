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
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\Exception\CustomerAlreadyInCompanyException;
use Thelia\Domain\Company\Service\CompanyGrantPolicy;
use Thelia\Domain\Company\Service\CompanyInvitationLimiter;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Domain\Company\Service\CompanyRoleResolver;

/**
 * The use of an invitation is written in the transaction of the link: when the link is refused,
 * the invitation is not spent. The test opens no transaction of its own, since one inside another
 * cannot be rolled back alone, and removes what it wrote.
 */
final class CompanyInvitationAtomicityTest extends CompanyDomainTestCase
{
    protected bool $useTransaction = false;

    public function testAnInvitationWhoseLinkIsRefusedIsNotSpent(): void
    {
        $company = $this->company('Atomic '.bin2hex(random_bytes(4)));
        $issuer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('atomic-'.bin2hex(random_bytes(4)).'@acme.test');
        $connection = $this->getPropelConnection();

        try {
            $clock = new MockClock('2026-11-18 10:00:00');
            $window = static fn (): RateLimiterFactory => new RateLimiterFactory(
                ['id' => 'test', 'policy' => 'sliding_window', 'limit' => 1000, 'interval' => '1 hour'],
                new InMemoryStorage(),
            );
            $build = fn (ClockInterface $clock): CompanyInvitationService => new CompanyInvitationService(
                $this->facade,
                new CompanyRoleResolver(),
                new CompanyGrantPolicy($this->reader, $this->checker),
                new CompanyInvitationLimiter($window(), $window(), static::getContainer()->get('request_stack')),
                $clock,
                'a-secret-of-the-test-shop',
                $this->reader,
            );
            $token = $build($clock)->createToken($company, (string) $invited->getEmail(), CompanyRoleCode::BUYER, $issuer, null, null, null);

            // The invited joins a company between the read of the invitation and the writing of the link.
            $joinsElsewhere = new class($clock, static function () use ($connection, $invited, $issuer): void {
                $connection->exec(\sprintf(
                    'INSERT INTO `company_customer` (`company_id`, `customer_id`, `company_role_id`) SELECT `company_id`, %d, `company_role_id` FROM `company_customer` WHERE `customer_id` = %d',
                    (int) $invited->getId(),
                    (int) $issuer->getId(),
                ));
            }) implements ClockInterface {
                private int $asked = 0;

                public function __construct(private readonly ClockInterface $clock, private readonly \Closure $hook)
                {
                }

                public function now(): \DateTimeImmutable
                {
                    if (2 === ++$this->asked) {
                        ($this->hook)();
                    }

                    return $this->clock->now();
                }
            };

            try {
                $build($joinsElsewhere)->accept($token, $invited);
                self::fail('The invited was taken in though it already belonged to a company.');
            } catch (CustomerAlreadyInCompanyException) {
            }

            self::assertSame(
                '0',
                (string) $connection->query('SELECT COUNT(*) FROM `company_invitation_use` WHERE `company_id` = '.(int) $company->getId())->fetchColumn(),
                'The use was kept though the link was refused.',
            );
            self::assertNotNull($build($clock)->read($token), 'The invitation can still be used.');
        } finally {
            $connection->exec('DELETE FROM `company_invitation_use` WHERE `company_id` = '.(int) $company->getId());
            $connection->exec('DELETE FROM `company_customer` WHERE `company_id` = '.(int) $company->getId());
            $connection->exec('DELETE FROM `company` WHERE `id` = '.(int) $company->getId());
            $connection->exec('DELETE FROM `customer` WHERE `id` IN ('.(int) $issuer->getId().', '.(int) $invited->getId().')');
        }
    }
}
