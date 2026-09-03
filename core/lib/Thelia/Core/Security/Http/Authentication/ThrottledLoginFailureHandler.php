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

namespace Thelia\Core\Security\Http\Authentication;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Thelia\Core\Security\RateLimiter\RateLimitedResponse;

/**
 * Answers a caller that ran out of login attempts with the refusal meant for
 * that, instead of one more "invalid credentials".
 *
 * The JWT bundle answers every authentication failure the same way, which is
 * right for a wrong password but wrong once attempts are being counted: a client
 * cannot tell it should back off, and a well-built one retries straight away.
 *
 * Every other failure is handed to the JWT bundle's own handler, so a wrong
 * password keeps the exact response it has always had, body included. The
 * handler is wrapped here rather than decorated, because a firewall inherits
 * from its configured failure handler as a parent definition: a decorator on the
 * bundle's service is resolved away before it reaches the firewall.
 */
final readonly class ThrottledLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private AuthenticationFailureHandler $ordinaryFailures,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return RateLimitedResponse::forRequest($request);
        }

        return $this->ordinaryFailures->onAuthenticationFailure($request, $exception);
    }
}
