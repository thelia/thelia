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

namespace Thelia\Tests\Unit\Core\Security\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Thelia\Core\Security\EventListener\AuthenticationFailureLogListener;

/**
 * The trace a refused authentication leaves.
 *
 * A limiter refuses an attempt but says nothing about it: without a record of
 * who aimed at what, someone working through a list of passwords is invisible
 * while it happens and unprovable afterwards. And the record must never carry
 * the password itself, whole or in part: a log is read, copied and shipped to
 * places the password database is not.
 */
final class AuthenticationFailureLogListenerTest extends TestCase
{
    private const string CALLER = '192.0.2.70';

    private const string SECRET = 'a-password-nobody-should-ever-log';

    public function testARefusedLoginNamesTheCallerAndTheIdentifier(): void
    {
        $logger = new RecordingLogger();
        $listener = new AuthenticationFailureLogListener($logger);

        $listener->onLoginFailure($this->loginFailure('/api/admin/login', 'thelia'));

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertStringContainsString(self::CALLER, $logger->records[0]['line']);
        self::assertStringContainsString('thelia', $logger->records[0]['line']);
        self::assertStringContainsString('/api/admin/login', $logger->records[0]['line']);
    }

    public function testAnAttemptOnAnIdentifierThatNamesNoAccountIsRecordedToo(): void
    {
        $logger = new RecordingLogger();
        $listener = new AuthenticationFailureLogListener($logger);

        $listener->onLoginFailure($this->loginFailure('/api/front/login', 'nobody@example.com'));

        self::assertStringContainsString('nobody@example.com', $logger->records[0]['line']);
    }

    public function testTheRecordNeverCarriesThePassword(): void
    {
        $logger = new RecordingLogger();
        $listener = new AuthenticationFailureLogListener($logger);

        $listener->onLoginFailure($this->loginFailure('/api/admin/login', 'thelia'));

        self::assertStringNotContainsString(self::SECRET, $logger->records[0]['line']);
        self::assertStringNotContainsString(substr(self::SECRET, 0, 8), $logger->records[0]['line']);
    }

    public function testARefusedTokenRefreshIsRecorded(): void
    {
        $logger = new RecordingLogger();
        $listener = new AuthenticationFailureLogListener($logger);

        $listener->onTokenRefreshResponse(
            $this->response('/api/admin/token/refresh', Response::HTTP_UNAUTHORIZED),
        );

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertStringContainsString(self::CALLER, $logger->records[0]['line']);
        self::assertStringContainsString('/api/admin/token/refresh', $logger->records[0]['line']);
    }

    public function testAnAcceptedTokenRefreshLeavesNoTrace(): void
    {
        $logger = new RecordingLogger();
        $listener = new AuthenticationFailureLogListener($logger);

        $listener->onTokenRefreshResponse($this->response('/api/admin/token/refresh', Response::HTTP_OK));

        self::assertSame([], $logger->records);
    }

    public function testAnyOtherResponseLeavesNoTrace(): void
    {
        $logger = new RecordingLogger();
        $listener = new AuthenticationFailureLogListener($logger);

        $listener->onTokenRefreshResponse($this->response('/api/front/products', Response::HTTP_UNAUTHORIZED));

        self::assertSame([], $logger->records);
    }

    private function loginFailure(string $path, string $identifier): LoginFailureEvent
    {
        $request = Request::create($path, 'POST', server: ['REMOTE_ADDR' => self::CALLER]);
        $passport = new Passport(
            new UserBadge($identifier),
            new PasswordCredentials(self::SECRET),
        );

        return new LoginFailureEvent(
            new BadCredentialsException(),
            $this->createMock(AuthenticatorInterface::class),
            $request,
            null,
            'adminLogin',
            $passport,
        );
    }

    private function response(string $path, int $status): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($path, 'POST', server: ['REMOTE_ADDR' => self::CALLER]),
            HttpKernelInterface::MAIN_REQUEST,
            new Response('', $status),
        );
    }
}

/**
 * Keeps every record as the single line a handler would write, so a test can
 * assert on what a reader of the log would actually see.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, line: string}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'line' => $message.' '.json_encode($context, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
        ];
    }
}
