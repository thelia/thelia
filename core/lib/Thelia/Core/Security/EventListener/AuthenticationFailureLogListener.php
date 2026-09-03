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

namespace Thelia\Core\Security\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Writes down every authentication the API refused.
 *
 * A limiter turns an attempt away but says nothing about it, so without this
 * there is no way to see someone working through a list of passwords while it
 * is happening, and no way to show afterwards that it happened. One line per
 * refusal, naming the caller and the identifier that was aimed at, is enough
 * for a human to read and for a log watcher to act on.
 *
 * What it must never write down is the password, whole or in part: a log is
 * read, copied, shipped and kept in places the password store is not. Only the
 * caller, the identifier, the endpoint and the kind of refusal go in.
 *
 * A refused token refresh is recorded from the response rather than from an
 * event, because those two endpoints are plain controllers: they answer 401
 * themselves, without the firewall ever raising a failure. Such a refusal names
 * no identifier — a refresh token is opaque, and a caller trying one has not
 * said who it claims to be.
 */
final readonly class AuthenticationFailureLogListener
{
    private const array TOKEN_REFRESH_PATHS = [
        '/api/admin/token/refresh',
        '/api/front/token/refresh',
    ];

    public function __construct(
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        $this->logger->warning('API login refused.', [
            'caller' => $request->getClientIp(),
            'identifier' => $this->identifierAimedAt($event),
            'endpoint' => $request->getPathInfo(),
            // The class, not the message: it says whether the attempt was
            // turned away for a bad password, a disabled account or a limit
            // reached, and it cannot carry anything the caller typed.
            'refusal' => $event->getException()::class,
        ]);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onTokenRefreshResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!\in_array(rtrim($request->getPathInfo(), '/'), self::TOKEN_REFRESH_PATHS, true)) {
            return;
        }

        if (Response::HTTP_UNAUTHORIZED !== $event->getResponse()->getStatusCode()) {
            return;
        }

        $this->logger->warning('API token refresh refused.', [
            'caller' => $request->getClientIp(),
            'endpoint' => $request->getPathInfo(),
        ]);
    }

    private function identifierAimedAt(LoginFailureEvent $event): ?string
    {
        $passport = $event->getPassport();

        if (null !== $passport && $passport->hasBadge(UserBadge::class)) {
            /** @var UserBadge $badge */
            $badge = $passport->getBadge(UserBadge::class);

            return $badge->getUserIdentifier();
        }

        $lastUsername = $event->getRequest()->attributes->get(SecurityRequestAttributes::LAST_USERNAME);

        return \is_string($lastUsername) ? $lastUsername : null;
    }
}
