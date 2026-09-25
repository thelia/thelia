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

namespace Thelia\Domain\Admin\TwoFactor;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;

final readonly class TwoFactorChallenge
{
    public const SESSION_KEY = 'thelia.admin_two_factor_challenge';
    public const LIFETIME_SECONDS = 300;
    public const MAX_ATTEMPTS = 5;

    public function start(
        SessionInterface $session,
        Admin $admin,
        bool $rememberMe,
        ?string $successUrl,
        bool $fromRememberMeCookie = false,
    ): void {
        $session->migrate(true);
        $session->set(self::SESSION_KEY, [
            'admin_id' => (int) $admin->getId(),
            'expires_at' => time() + self::LIFETIME_SECONDS,
            'attempts' => 0,
            'remember_me' => $rememberMe,
            'success_url' => $successUrl,
            'from_remember_me_cookie' => $fromRememberMeCookie,
        ]);
    }

    public function pendingAdmin(SessionInterface $session): ?Admin
    {
        $challenge = $this->current($session);

        return null === $challenge ? null : AdminQuery::create()->findPk($challenge['admin_id']);
    }

    public function rememberMeRequested(SessionInterface $session): bool
    {
        return true === ($this->current($session)['remember_me'] ?? false);
    }

    public function successUrl(SessionInterface $session): ?string
    {
        $successUrl = $this->current($session)['success_url'] ?? null;

        return \is_string($successUrl) ? $successUrl : null;
    }

    public function startedFromRememberMeCookie(SessionInterface $session): bool
    {
        return true === ($this->current($session)['from_remember_me_cookie'] ?? false);
    }

    public function recordFailedAttempt(SessionInterface $session): bool
    {
        $challenge = $this->current($session);

        if (null === $challenge) {
            return false;
        }

        ++$challenge['attempts'];

        if ($challenge['attempts'] >= self::MAX_ATTEMPTS) {
            $this->clear($session);

            return false;
        }

        $session->set(self::SESSION_KEY, $challenge);

        return true;
    }

    public function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }

    /**
     * @return array{admin_id: int, expires_at: int, attempts: int, remember_me: bool, success_url: ?string, from_remember_me_cookie: bool}|null
     */
    private function current(SessionInterface $session): ?array
    {
        $challenge = $session->get(self::SESSION_KEY);

        if (!\is_array($challenge) || !isset($challenge['admin_id'], $challenge['expires_at'], $challenge['attempts'])) {
            return null;
        }

        if ($challenge['expires_at'] < time() || $challenge['attempts'] >= self::MAX_ATTEMPTS) {
            $this->clear($session);

            return null;
        }

        return $challenge;
    }
}
