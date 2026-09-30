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
    private const SESSION_KEY = 'thelia.token_provider';
    private const TOKEN = 'a3f1c9e07b2d4f6a8c0e1b3d5f7a9c2e';

    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        $this->deprecations = [];
        set_error_handler(function (int $level, string $message): bool {
            $this->deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testTheSessionTokenIsAccepted(): void
    {
        self::assertTrue($this->provider($this->requestWithSession())->checkToken(self::TOKEN));
    }

    public function testATokenPostedInTheBodyIsAcceptedWithoutDeprecation(): void
    {
        $request = $this->requestWithSession(body: ['_token' => self::TOKEN]);

        self::assertTrue($this->provider($request)->checkRequestToken($request));
        self::assertSame([], $this->deprecations);
    }

    public function testATokenSentInTheHeaderIsAcceptedWithoutDeprecation(): void
    {
        $request = $this->requestWithSession(headers: ['X-CSRF-Token' => self::TOKEN]);

        self::assertTrue($this->provider($request)->checkRequestToken($request));
        self::assertSame([], $this->deprecations);
    }

    public function testATokenInTheUrlIsStillAcceptedButDeprecated(): void
    {
        $request = $this->requestWithSession(query: ['_token' => self::TOKEN]);

        self::assertTrue($this->provider($request)->checkRequestToken($request));
        self::assertCount(1, $this->deprecations);
        self::assertStringContainsString('query string', $this->deprecations[0]);
    }

    public function testAValueACallerReadFromTheUrlIsDeprecatedToo(): void
    {
        // The Twig back office and many modules read `_token` themselves and pass the string.
        $request = $this->requestWithSession(query: ['_token' => self::TOKEN]);

        self::assertTrue($this->provider($request)->checkToken(self::TOKEN));
        self::assertCount(1, $this->deprecations);
    }

    public function testAValueACallerReadFromTheBodyIsNotDeprecated(): void
    {
        $request = $this->requestWithSession(body: ['_token' => self::TOKEN]);

        self::assertTrue($this->provider($request)->checkToken(self::TOKEN));
        self::assertSame([], $this->deprecations);
    }

    public function testTheBodyWinsOverTheUrl(): void
    {
        $request = $this->requestWithSession(query: ['_token' => 'wrong'], body: ['_token' => self::TOKEN]);

        self::assertTrue($this->provider($request)->checkRequestToken($request));
        self::assertSame([], $this->deprecations);
    }

    public function testTheUrlIsRefusedOnceTheStrictModeIsOn(): void
    {
        $request = $this->requestWithSession(query: ['_token' => self::TOKEN]);

        $this->expectException(TokenAuthenticationException::class);

        $this->provider($request, acceptTokenInQueryString: false)->checkRequestToken($request);
    }

    public function testAWrongValueIsRefused(): void
    {
        $request = $this->requestWithSession();

        $this->expectException(TokenAuthenticationException::class);

        $this->provider($request)->checkToken('wrong');
    }

    public function testAWrongTokenPostedInTheBodyIsRefused(): void
    {
        $request = $this->requestWithSession(body: ['_token' => 'wrong']);

        $this->expectException(TokenAuthenticationException::class);

        $this->provider($request)->checkRequestToken($request);
    }

    public function testARequestWithoutTokenIsRefused(): void
    {
        $request = $this->requestWithSession();

        $this->expectException(TokenAuthenticationException::class);

        $this->provider($request)->checkRequestToken($request);
    }

    public function testTheComparisonRunsInConstantTime(): void
    {
        $source = (string) file_get_contents((string) (new \ReflectionClass(TokenProvider::class))->getFileName());

        self::assertStringContainsString('hash_equals($this->token, $entryValue)', $source);
        self::assertStringNotContainsString('$this->token !== $entryValue', $source);
    }

    private function provider(Request $request, bool $acceptTokenInQueryString = true): TokenProvider
    {
        $stack = new RequestStack();
        $stack->push($request);

        return new TokenProvider($stack, $this->createStub(TranslatorInterface::class), self::SESSION_KEY, $acceptTokenInQueryString);
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $body
     * @param array<string, string> $headers
     */
    private function requestWithSession(array $query = [], array $body = [], array $headers = []): Request
    {
        $request = new Request($query, $body, [], [], [], [], null);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $session = new Session(new MockArraySessionStorage());
        $session->set(self::SESSION_KEY, self::TOKEN);
        $request->setSession($session);

        return $request;
    }
}
