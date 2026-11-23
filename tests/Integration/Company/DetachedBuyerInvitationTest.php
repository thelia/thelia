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

use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\Exception\InvalidCompanyInvitationException;
use Thelia\Domain\Company\Service\CompanyGrantPolicy;
use Thelia\Domain\Company\Service\CompanyInvitationLimiter;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Domain\Company\Service\CompanyRoleResolver;

/**
 * A buyer the company detached does not come back on their own: the invitation they used stays
 * used, and so must any other invitation sent to the same address before they were detached (a
 * resent invitation, for instance). Otherwise the detached person rejoins the company, with the
 * role the invitation gives, and reads its orders and shared addresses again.
 */
final class DetachedBuyerInvitationTest extends CompanyDomainTestCase
{
    public function testASecondInvitationSentBeforeTheDetachmentDoesNotLetTheDetachedBuyerBackIn(): void
    {
        $clock = new MockClock('2026-11-18 10:00:00');
        $service = $this->service($clock);

        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $invited = $this->customer('leaver@acme.test');

        // The invitation, then the same invitation sent again a minute later.
        $first = $service->createToken($company, 'leaver@acme.test', CompanyRoleCode::BUYER, $administrator, null, null, null);
        $clock->sleep(60);
        $resent = $service->createToken($company, 'leaver@acme.test', CompanyRoleCode::BUYER, $administrator, null, null, null);
        self::assertNotSame($first, $resent);

        $service->accept($first, $invited);
        self::assertNotNull($this->buyerRow($invited));

        // The administrator of the company detaches the buyer.
        $this->facade->detach($invited);
        $this->reader->forget((int) $invited->getId());
        self::assertNull($this->buyerRow($invited));

        $rejoined = true;

        try {
            $service->accept($resent, $invited);
        } catch (InvalidCompanyInvitationException) {
            $rejoined = false;
        }

        self::assertFalse($rejoined, 'The detached buyer rejoined the company with the invitation sent before the detachment.');
        self::assertNull($this->buyerRow($invited), 'The detached buyer is a buyer of the company again.');
    }

    private function service(MockClock $clock): CompanyInvitationService
    {
        $window = static fn (): RateLimiterFactory => new RateLimiterFactory(
            ['id' => 'pipe', 'policy' => 'sliding_window', 'limit' => 1000, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );

        return new CompanyInvitationService(
            $this->facade,
            new CompanyRoleResolver(),
            new CompanyGrantPolicy($this->reader, $this->checker),
            new CompanyInvitationLimiter($window(), $window(), static::getContainer()->get('request_stack')),
            $clock,
            'a-secret-of-the-test-shop',
            $this->reader,
        );
    }
}
