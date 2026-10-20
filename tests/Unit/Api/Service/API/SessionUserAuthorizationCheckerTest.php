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

namespace Thelia\Tests\Unit\Api\Service\API;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Thelia\Api\Service\API\SessionUserAuthorizationChecker;
use Thelia\Model\Customer;

final class SessionUserAuthorizationCheckerTest extends TestCase
{
    public function testARoleIsCheckedAgainstTheSessionUser(): void
    {
        $checker = new SessionUserAuthorizationChecker(new Customer(), null);

        self::assertTrue($checker->isGranted('ROLE_CUSTOMER'));
        self::assertFalse($checker->isGranted('ROLE_ADMIN'));
    }

    public function testAnyOtherAttributeIsDecidedByTheDecoratedCheckerWithTheAccessDecision(): void
    {
        $subject = new \stdClass();
        $accessDecision = new AccessDecision();

        $decorated = $this->createMock(AuthorizationCheckerInterface::class);
        $decorated->expects(self::once())
            ->method('isGranted')
            ->with('EDIT', $subject, $accessDecision)
            ->willReturn(true);

        self::assertTrue((new SessionUserAuthorizationChecker(null, $decorated))->isGranted('EDIT', $subject, $accessDecision));
    }
}
