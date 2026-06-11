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

namespace Thelia\Tests\Unit\BackOfficeDefaultTwig\Service\Admin;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Form\Legacy\LegacyFormEventBridge;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventDispatcher;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormErrorRenderer;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormValidator;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Lang\LangCreateEvent;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\TokenProvider;

final class AdminFormActionTest extends TestCase
{
    public function testSubmitRunsTheFullPipelineOnSuccess(): void
    {
        $access = $this->createMock(AdminAccessChecker::class);
        $access->expects(self::once())->method('check')->willReturn(null);

        $form = $this->createMock(FormInterface::class);

        $validator = $this->createMock(AdminFormValidator::class);
        $validator->expects(self::once())->method('validate')->with($form)->willReturn($form);

        $event = new LangCreateEvent();
        $eventFactory = static fn (FormInterface $f): LangCreateEvent => $event;

        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::once())->method('dispatch')->with($event, 'lang.create');

        $logger = $this->createMock(AdminLogger::class);
        $logger->expects(self::once())->method('log')->with('admin.lang', 'CREATE', 'Lang created (1)', 1);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())->method('generate')->with('admin.lang.default', [])->willReturn('/admin/lang');

        $action = new AdminFormAction(
            $access,
            $validator,
            $logger,
            $this->createMock(AdminFormErrorRenderer::class),
            $events,
            $urls,
            $this->stubTranslator(),
            $this->createMock(TokenProvider::class),
            new LegacyFormEventBridge(new EventDispatcher(), new RequestStack()),
        );

        $response = $action->submit(
            resource: 'admin.lang',
            access: 'CREATE',
            form: $form,
            eventName: 'lang.create',
            eventFactory: $eventFactory,
            actionLabel: 'Language creation',
            successRoute: 'admin.lang.default',
            renderError: static fn (\Throwable $e): Response => new Response('error', 400),
            describeForLog: static fn (LangCreateEvent $e): array => ['Lang created (1)', 1],
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/lang', $response->getTargetUrl());
    }

    public function testSubmitInvokesRenderErrorWhenValidationFails(): void
    {
        $access = $this->createMock(AdminAccessChecker::class);
        $access->expects(self::once())->method('check')->willReturn(null);

        $form = $this->createMock(FormInterface::class);

        $validator = $this->createMock(AdminFormValidator::class);
        $validator->expects(self::once())
            ->method('validate')
            ->willThrowException(new FormValidationException('field required'));

        $errorRenderer = $this->createMock(AdminFormErrorRenderer::class);
        $errorRenderer->expects(self::once())
            ->method('setup')
            ->with('Language creation', 'field required', $form, self::isInstanceOf(FormValidationException::class));

        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::never())->method('dispatch');

        $renderError = static fn (\Throwable $e): Response => new Response('rendered '.$e->getMessage(), 400);

        $action = new AdminFormAction(
            $access,
            $validator,
            $this->createMock(AdminLogger::class),
            $errorRenderer,
            $events,
            $this->createMock(UrlGeneratorInterface::class),
            $this->stubTranslator(),
            $this->createMock(TokenProvider::class),
            new LegacyFormEventBridge(new EventDispatcher(), new RequestStack()),
        );

        $response = $action->submit(
            resource: 'admin.lang',
            access: 'CREATE',
            form: $form,
            eventName: 'lang.create',
            eventFactory: static fn () => new LangCreateEvent(),
            actionLabel: 'Language creation',
            successRoute: 'admin.lang.default',
            renderError: $renderError,
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('rendered field required', $response->getContent());
    }

    public function testTokenActionRedirectsOnSuccess(): void
    {
        $access = $this->createMock(AdminAccessChecker::class);
        $access->expects(self::once())->method('check')->willReturn(null);

        $tokens = $this->createMock(TokenProvider::class);
        $tokens->expects(self::once())->method('checkToken')->with('abc');

        $event = new LangCreateEvent();
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::once())->method('dispatch')->with($event, 'lang.toggle');

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())->method('generate')->with('admin.lang.default', [])->willReturn('/admin/lang');

        $request = new Request(['_token' => 'abc']);

        $action = new AdminFormAction(
            $access,
            $this->createMock(AdminFormValidator::class),
            $this->createMock(AdminLogger::class),
            $this->createMock(AdminFormErrorRenderer::class),
            $events,
            $urls,
            $this->stubTranslator(),
            $tokens,
            new LegacyFormEventBridge(new EventDispatcher(), new RequestStack()),
        );

        $response = $action->tokenAction(
            resource: 'admin.lang',
            access: 'UPDATE',
            request: $request,
            event: $event,
            eventName: 'lang.toggle',
            actionLabel: 'Language toggle',
            successRoute: 'admin.lang.default',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/lang', $response->getTargetUrl());
    }

    public function testTokenActionRedirectsBackOnInvalidToken(): void
    {
        $access = $this->createMock(AdminAccessChecker::class);
        $access->expects(self::once())->method('check')->willReturn(null);

        $tokens = $this->createMock(TokenProvider::class);
        $tokens->expects(self::once())
            ->method('checkToken')
            ->willThrowException(new \RuntimeException('invalid token'));

        $errorRenderer = $this->createMock(AdminFormErrorRenderer::class);
        $errorRenderer->expects(self::once())->method('setup');

        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::never())->method('dispatch');

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())->method('generate')->with('admin.lang.default', [])->willReturn('/admin/lang');

        $action = new AdminFormAction(
            $access,
            $this->createMock(AdminFormValidator::class),
            $this->createMock(AdminLogger::class),
            $errorRenderer,
            $events,
            $urls,
            $this->stubTranslator(),
            $tokens,
            new LegacyFormEventBridge(new EventDispatcher(), new RequestStack()),
        );

        $response = $action->tokenAction(
            resource: 'admin.lang',
            access: 'UPDATE',
            request: new Request(['_token' => 'bad']),
            event: new LangCreateEvent(),
            eventName: 'lang.toggle',
            actionLabel: 'Language toggle',
            successRoute: 'admin.lang.default',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/lang', $response->getTargetUrl());
    }

    public function testSubmitReturnsDenialResponseWhenAccessIsForbidden(): void
    {
        $denied = new Response('denied', 403);

        $access = $this->createMock(AdminAccessChecker::class);
        $access->expects(self::once())->method('check')->willReturn($denied);

        $validator = $this->createMock(AdminFormValidator::class);
        $validator->expects(self::never())->method('validate');

        $action = new AdminFormAction(
            $access,
            $validator,
            $this->createMock(AdminLogger::class),
            $this->createMock(AdminFormErrorRenderer::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->stubTranslator(),
            $this->createMock(TokenProvider::class),
            new LegacyFormEventBridge(new EventDispatcher(), new RequestStack()),
        );

        $response = $action->submit(
            resource: 'admin.lang',
            access: 'CREATE',
            form: $this->createMock(FormInterface::class),
            eventName: 'lang.create',
            eventFactory: static fn () => new LangCreateEvent(),
            actionLabel: 'Language creation',
            successRoute: 'admin.lang.default',
            renderError: static fn (\Throwable $e): Response => new Response('error'),
        );

        self::assertSame($denied, $response);
    }

    private function stubTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
