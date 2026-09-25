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

namespace Thelia\Core\Security\RefreshToken;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Model\Admin;
use Thelia\Model\Customer;

/**
 * Augments Lexik's authentication success response with a refresh token.
 *
 * Scope is derived from the user concrete class so the same listener
 * covers both admin and customer firewalls without per-firewall config.
 */
final readonly class AuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RefreshTokenService $refreshTokens,
        private AdminTwoFactorManager $twoFactorManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        $scope = match (true) {
            $user instanceof Admin => RefreshTokenService::SCOPE_ADMIN,
            $user instanceof Customer => RefreshTokenService::SCOPE_CUSTOMER,
            default => null,
        };

        if ($scope === null) {
            return;
        }

        $username = method_exists($user, 'getUserIdentifier')
            ? $user->getUserIdentifier()
            : (method_exists($user, 'getUsername') ? $user->getUsername() : null);

        if ($username === null) {
            return;
        }

        $data = $event->getData();
        $data['refresh_token'] = $this->refreshTokens->issue(
            $username,
            $scope,
            $user instanceof Admin ? $this->twoFactorManager->enrolmentMarkOf($user) : null,
        );
        $data['refresh_token_ttl'] = $this->refreshTokens->ttl();
        $event->setData($data);
    }
}
