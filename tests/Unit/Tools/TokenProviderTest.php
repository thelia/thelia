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

namespace Thelia\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Tools\TokenProvider;

final class TokenProviderTest extends TestCase
{
    private const TOKEN_NAME = 'thelia.token_provider';

    public function testTokenIsStoredInASessionThatAppearsAfterInstantiation(): void
    {
        // The service is often built before the session is started, e.g. while the
        // kernel.request listeners are being instantiated.
        $requestStack = new RequestStack();
        $tokenProvider = $this->createTokenProvider($requestStack);

        $session = $this->pushRequestWithSession($requestStack);

        $token = $tokenProvider->assignToken();

        self::assertSame($token, $session->get(self::TOKEN_NAME));
    }

    public function testTokenAssignedOnAPreviousRequestIsAccepted(): void
    {
        $requestStack = new RequestStack();
        $tokenProvider = $this->createTokenProvider($requestStack);

        $session = $this->pushRequestWithSession($requestStack);
        $session->set(self::TOKEN_NAME, 'a-token-assigned-before');

        self::assertTrue($tokenProvider->checkToken('a-token-assigned-before'));
    }

    public function testAnInvalidTokenIsRejected(): void
    {
        $requestStack = new RequestStack();
        $tokenProvider = $this->createTokenProvider($requestStack);

        $session = $this->pushRequestWithSession($requestStack);
        $session->set(self::TOKEN_NAME, 'a-token-assigned-before');

        $this->expectException(TokenAuthenticationException::class);

        $tokenProvider->checkToken('another-token');
    }

    public function testAMissingTokenIsRejected(): void
    {
        $requestStack = new RequestStack();
        $tokenProvider = $this->createTokenProvider($requestStack);

        $this->pushRequestWithSession($requestStack);

        $this->expectException(TokenAuthenticationException::class);

        $tokenProvider->checkToken('any-token');
    }

    private function createTokenProvider(RequestStack $requestStack): TokenProvider
    {
        return new TokenProvider(
            $requestStack,
            $this->createMock(TranslatorInterface::class),
            self::TOKEN_NAME
        );
    }

    private function pushRequestWithSession(RequestStack $requestStack): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $request = new Request();
        $request->setSession($session);

        $requestStack->push($request);

        return $session;
    }
}
