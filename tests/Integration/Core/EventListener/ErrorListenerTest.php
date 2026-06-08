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

namespace Thelia\Tests\Integration\Core\EventListener;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\EventListener\ErrorListener;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * Guards {@see ErrorListener::defaultErrorFallback()} against masking every
 * exception as a 500. The Thelia error page must carry the real HTTP status of
 * HTTP exceptions (404, 403, 405, ...) so crawlers, the BO and the API can tell
 * a "method not allowed" or "not found" apart from a genuine server failure.
 *
 * Booted as an integration test because the listener reads the error-page name
 * from the database via ConfigQuery.
 */
final class ErrorListenerTest extends IntegrationTestCase
{
    public function testHttpExceptionStatusIsPreserved(): void
    {
        $assigned = [];
        $event = $this->handle(
            new MethodNotAllowedHttpException(['POST'], 'Method GET is not allowed.'),
            $assigned,
        );

        self::assertNotNull($event->getResponse());
        self::assertSame(405, $event->getResponse()->getStatusCode());
        self::assertSame(405, $assigned['status_code'] ?? null);
        self::assertSame('POST', $event->getResponse()->headers->get('Allow'));
    }

    public function testNonHttpExceptionFallsBackToInternalServerError(): void
    {
        $assigned = [];
        $event = $this->handle(new \RuntimeException('boom'), $assigned);

        self::assertNotNull($event->getResponse());
        self::assertSame(500, $event->getResponse()->getStatusCode());
        self::assertSame(500, $assigned['status_code'] ?? null);
    }

    /**
     * @param array<string, mixed> $assigned captured parser assignments
     */
    private function handle(\Throwable $throwable, array &$assigned): ExceptionEvent
    {
        $parser = $this->createMock(ParserInterface::class);
        $parser->method('hasTemplateDefinition')->willReturn(true);
        $parser->method('render')->willReturn('error page');
        $parser->method('assign')->willReturnCallback(
            static function (array|string $variable, mixed $value = null) use (&$assigned): void {
                if (\is_string($variable)) {
                    $assigned[$variable] = $value;
                }
            },
        );

        $parserResolver = $this->createMock(ParserResolver::class);
        $parserResolver->method('getParserByCurrentRequest')->willReturn($parser);

        $listener = new ErrorListener(
            'prod',
            $parserResolver,
            $this->createMock(SecurityContext::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        $listener->defaultErrorFallback($event);

        return $event;
    }
}
