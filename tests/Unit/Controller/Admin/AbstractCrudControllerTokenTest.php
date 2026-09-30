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

namespace Thelia\Tests\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Event\ActiveRecordEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Controller\Admin\AbstractCrudController;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Tools\TokenProvider;

/**
 * The toggle, position and delete actions every CRUD controller inherits change data:
 * each of them must be refused without the session token.
 */
final class AbstractCrudControllerTokenTest extends TestCase
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

    public function testAToggleWithoutTokenIsRefused(): void
    {
        $request = $this->request();

        $response = $this->controller($request)->setToggleVisibilityAction($this->dispatcherExpecting(0));

        self::assertSame(Response::HTTP_FORBIDDEN, $response?->getStatusCode());
    }

    public function testAToggleWithAWrongTokenIsRefused(): void
    {
        $request = $this->request(body: ['_token' => 'wrong']);

        $response = $this->controller($request)->setToggleVisibilityAction($this->dispatcherExpecting(0));

        self::assertSame(Response::HTTP_FORBIDDEN, $response?->getStatusCode());
    }

    public function testAToggleWithTheTokenInTheBodyIsApplied(): void
    {
        $request = $this->request(body: ['_token' => self::TOKEN]);

        $response = $this->controller($request)->setToggleVisibilityAction($this->dispatcherExpecting(1));

        self::assertSame(Response::HTTP_OK, $response?->getStatusCode());
        self::assertSame([], $this->deprecations);
    }

    public function testAToggleWithTheTokenInTheHeaderIsApplied(): void
    {
        $request = $this->request(headers: ['X-CSRF-Token' => self::TOKEN]);

        $response = $this->controller($request)->setToggleVisibilityAction($this->dispatcherExpecting(1));

        self::assertSame(Response::HTTP_OK, $response?->getStatusCode());
        self::assertSame([], $this->deprecations);
    }

    public function testAToggleWithTheTokenInTheUrlIsAppliedWithADeprecation(): void
    {
        $request = $this->request(query: ['_token' => self::TOKEN]);

        $response = $this->controller($request)->setToggleVisibilityAction($this->dispatcherExpecting(1));

        self::assertSame(Response::HTTP_OK, $response?->getStatusCode());
        self::assertCount(1, $this->deprecations);
    }

    public function testAPositionChangeWithoutTokenIsRefused(): void
    {
        $request = $this->request(query: ['mode' => 'up']);

        $response = $this->controller($request)->updatePositionAction($request, $this->dispatcherExpecting(0));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAPositionChangeWithAWrongTokenIsRefused(): void
    {
        $request = $this->request(query: ['mode' => 'up', '_token' => 'wrong']);

        $response = $this->controller($request)->updatePositionAction($request, $this->dispatcherExpecting(0));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAPositionChangeWithTheTokenInTheBodyIsApplied(): void
    {
        $request = $this->request(body: ['mode' => 'up', '_token' => self::TOKEN]);

        $response = $this->controller($request)->updatePositionAction($request, $this->dispatcherExpecting(1));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('list', $response->getContent());
    }

    public function testAPositionChangeWithTheTokenInTheUrlIsAppliedWithADeprecation(): void
    {
        $request = $this->request(query: ['mode' => 'up', '_token' => self::TOKEN]);

        $response = $this->controller($request)->updatePositionAction($request, $this->dispatcherExpecting(1));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('list', $response->getContent());
        self::assertCount(1, $this->deprecations);
    }

    public function testADeletionReadsTheTokenFromTheBody(): void
    {
        $request = $this->request(body: ['_token' => self::TOKEN]);

        $response = $this->controller($request)->deleteAction($request, $this->tokenProvider($request), $this->dispatcherExpecting(1), $this->createStub(ParserContext::class));

        self::assertSame('list', $response->getContent());
        self::assertSame([], $this->deprecations);
    }

    public function testADeletionWithoutTokenIsRefused(): void
    {
        $request = $this->request();

        $response = $this->controller($request)->deleteAction($request, $this->tokenProvider($request), $this->dispatcherExpecting(0), $this->createStub(ParserContext::class));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function dispatcherExpecting(int $dispatches): EventDispatcherInterface
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::exactly($dispatches))->method('dispatch')->willReturnArgument(0);

        return $dispatcher;
    }

    private function tokenProvider(Request $request): TokenProvider
    {
        $stack = new RequestStack();
        $stack->push($request);

        return new TokenProvider($stack, $this->createStub(TranslatorInterface::class), self::SESSION_KEY);
    }

    private function controller(Request $request): AbstractCrudController
    {
        $stack = new RequestStack();
        $stack->push($request);

        $controller = new class extends AbstractCrudController {
            public function __construct()
            {
                parent::__construct('item', null, null, 'admin.item', null, null, 'item.delete', 'item.toggle', 'item.position');
            }

            protected function checkAuth(mixed $resources, mixed $modules, mixed $accesses): ?Response
            {
                return null;
            }

            protected function errorPage(\Exception|string $message, int $status = 500): Response
            {
                return new Response($message instanceof \Exception ? $message->getMessage() : $message, $status);
            }

            protected function renderAfterDeleteError(ParserContext $parserContext, \Exception $e): Response
            {
                return new Response($e->getMessage());
            }

            protected function createToggleVisibilityEvent(): ActionEvent
            {
                return new class extends ActionEvent {};
            }

            protected function createUpdatePositionEvent(int $positionChangeMode, int $positionValue): ActionEvent
            {
                return new class extends ActionEvent {};
            }

            protected function getObjectFromEvent(Event $event): mixed
            {
                return null;
            }

            protected function getCreationForm(): ?BaseForm
            {
                return null;
            }

            protected function getUpdateForm(): ?BaseForm
            {
                return null;
            }

            protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
            {
                throw new \LogicException('Not used.');
            }

            protected function getCreationEvent(array $formData): ActionEvent|ActiveRecordEvent|null
            {
                return null;
            }

            protected function getUpdateEvent(array $formData): ActionEvent|ActiveRecordEvent|null
            {
                return null;
            }

            protected function getDeleteEvent(): ActiveRecordEvent|ActionEvent|null
            {
                return new class extends ActionEvent {};
            }

            protected function getExistingObject(): ?ActiveRecordInterface
            {
                return null;
            }

            protected function getObjectLabel(ActiveRecordInterface $object): ?string
            {
                return null;
            }

            protected function getObjectId(ActiveRecordInterface $object): int
            {
                return 0;
            }

            protected function renderListTemplate(string $currentOrder): Response
            {
                return new Response('list');
            }

            protected function renderEditionTemplate(): Response
            {
                return new Response('edit');
            }

            protected function redirectToEditionTemplate(): Response|RedirectResponse
            {
                return new Response('edit');
            }

            protected function redirectToListTemplate(): Response|RedirectResponse
            {
                return new Response('list');
            }
        };

        $controller->requestStack = $stack;
        $controller->tokenProvider = $this->tokenProvider($request);

        return $controller;
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $body
     * @param array<string, string> $headers
     */
    private function request(array $query = [], array $body = [], array $headers = []): Request
    {
        $request = new Request($query, $body);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $session = new Session(new MockArraySessionStorage());
        $session->set(self::SESSION_KEY, self::TOKEN);
        $request->setSession($session);

        return $request;
    }
}
