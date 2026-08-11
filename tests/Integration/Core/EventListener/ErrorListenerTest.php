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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\EventListener\ErrorListener;
use Thelia\Core\Security\Exception\AuthenticationException;
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
     * When the error page renders successfully, handleException() sets a
     * response on the kernel.exception event, which stops propagation: any
     * listener registered after it never runs. logException() must therefore
     * run BEFORE handleException(), otherwise production errors whose error
     * page renders fine are never logged anywhere.
     */
    public function testExceptionIsLoggedEvenWhenErrorPageIsRendered(): void
    {
        [$listener, $event] = $this->dispatchThroughRealPriorities(new \RuntimeException('boom'));

        self::assertNotNull($event->getResponse(), 'the error page should have been rendered');
        self::assertTrue($listener->logExceptionCalled, 'the original exception must be logged before the error page response stops event propagation');
    }

    /**
     * Authentication redirects are normal flow: authenticationException() runs
     * first (priority 100) and must keep short-circuiting logException() so
     * every login redirect does not pollute the error log.
     */
    public function testAuthenticationRedirectIsNotLoggedAsAnError(): void
    {
        [$listener, $event] = $this->dispatchThroughRealPriorities(new AuthenticationException('login required'));

        self::assertNotNull($event->getResponse());
        self::assertTrue($event->getResponse()->isRedirect());
        self::assertFalse($listener->logExceptionCalled, 'authentication redirects must not be logged as uncaught exceptions');
    }

    /**
     * Dispatches a kernel.exception event through a real EventDispatcher with
     * the listener methods registered at their attribute-declared priorities,
     * in method-declaration order — the exact wiring production uses.
     *
     * @return array{0: ErrorListener&object{logExceptionCalled: bool}, 1: ExceptionEvent}
     */
    private function dispatchThroughRealPriorities(\Throwable $throwable): array
    {
        $parserResolver = $this->createMock(ParserResolver::class);
        $parserResolver->method('getParserByCurrentRequest')
            ->willThrowException(new \Exception('no parser in this test'));

        // Simulates the prod wiring where THELIA_HANDLE_ERROR successfully
        // renders the Thelia error page and puts it on the event.
        $innerDispatcher = $this->createMock(EventDispatcherInterface::class);
        $innerDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event): object {
                if ($event instanceof ExceptionEvent) {
                    $event->setResponse(new Response('error page', 500));
                }

                return $event;
            },
        );

        $listener = new class('prod', $parserResolver, $this->createMock(SecurityContext::class), $innerDispatcher) extends ErrorListener {
            public bool $logExceptionCalled = false;

            public function logException(ExceptionEvent $event): void
            {
                $this->logExceptionCalled = true;
            }
        };

        $dispatcher = new EventDispatcher();
        foreach ((new \ReflectionClass(ErrorListener::class))->getMethods() as $method) {
            foreach ($method->getAttributes(AsEventListener::class) as $attribute) {
                $arguments = $attribute->getArguments();
                if (KernelEvents::EXCEPTION === ($arguments['event'] ?? null)) {
                    $dispatcher->addListener(
                        KernelEvents::EXCEPTION,
                        [$listener, $method->getName()],
                        $arguments['priority'] ?? 0,
                    );
                }
            }
        }

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        $dispatcher->dispatch($event, KernelEvents::EXCEPTION);

        return [$listener, $event];
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
