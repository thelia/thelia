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

namespace Thelia\Tests\Unit\Domain\Admin\TwoFactor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Domain\Admin\TwoFactor\TwoFactorChallenge;
use Thelia\Model\Admin;

final class TwoFactorChallengeTest extends TestCase
{
    public function testAChallengeRemembersWhatTheSignInAskedFor(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $challenge = new TwoFactorChallenge();

        $challenge->start($session, $this->admin(), true, '/admin/orders');

        self::assertTrue($challenge->rememberMeRequested($session));
        self::assertSame('/admin/orders', $challenge->successUrl($session));
        self::assertFalse($challenge->startedFromRememberMeCookie($session));
    }

    public function testAnExpiredChallengeIsForgotten(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $challenge = new TwoFactorChallenge();
        $challenge->start($session, $this->admin(), true, '/admin');

        $state = $session->get(TwoFactorChallenge::SESSION_KEY);
        $state['expires_at'] = time() - 1;
        $session->set(TwoFactorChallenge::SESSION_KEY, $state);

        self::assertFalse($challenge->rememberMeRequested($session));
        self::assertNull($challenge->successUrl($session));
        self::assertFalse($session->has(TwoFactorChallenge::SESSION_KEY));
        self::assertFalse($challenge->recordFailedAttempt($session));
    }

    public function testTheLastAllowedFailureEndsTheChallenge(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $challenge = new TwoFactorChallenge();
        $challenge->start($session, $this->admin(), false, null);

        for ($attempt = 1; $attempt < TwoFactorChallenge::MAX_ATTEMPTS; ++$attempt) {
            self::assertTrue($challenge->recordFailedAttempt($session));
        }

        self::assertFalse($challenge->recordFailedAttempt($session));
        self::assertFalse($session->has(TwoFactorChallenge::SESSION_KEY));
    }

    public function testTheChallengeNeverPutsTheAdministratorInTheAdminSessionKey(): void
    {
        $session = new Session(new MockArraySessionStorage());

        (new TwoFactorChallenge())->start($session, $this->admin(), true, '/admin');

        self::assertFalse($session->has('thelia.admin_user'));
        self::assertSame([TwoFactorChallenge::SESSION_KEY], array_keys($session->all()));
    }

    public function testStartingAChallengeGivesTheSessionANewId(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $idBeforeThePassword = $session->getId();

        (new TwoFactorChallenge())->start($session, $this->admin(), false, null);

        self::assertNotSame($idBeforeThePassword, $session->getId());
    }

    private function admin(): Admin
    {
        return (new Admin())->setId(42);
    }
}
