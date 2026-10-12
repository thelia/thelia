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

use PHPUnit\Framework\Attributes\DataProvider;
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
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Tools\TokenProvider;

/**
 * What a module controller built on the CRUD base class relies on: the follow-up hooks it can
 * override after a deletion or a position change, the module it declares to the access check,
 * and how a position change reads its mode and position from the request.
 */
final class AbstractCrudControllerHooksTest extends TestCase
{
    private const SESSION_KEY = 'thelia.token_provider';
    private const TOKEN = 'a3f1c9e07b2d4f6a8c0e1b3d5f7a9c2e';

    public function testTheModuleOfTheControllerIsDeclaredToTheAccessCheck(): void
    {
        $controller = $this->controller($this->request(), moduleCode: 'Acme');
        $controller->authResponse = new Response('denied', Response::HTTP_FORBIDDEN);

        $response = $controller->defaultAction();

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame([['admin.item', ['Acme']]], $controller->authChecks);
    }

    public function testAControllerOfTheCoreDeclaresNoModuleToTheAccessCheck(): void
    {
        $controller = $this->controller($this->request());
        $controller->authResponse = new Response('denied', Response::HTTP_FORBIDDEN);

        $controller->defaultAction();

        self::assertSame([['admin.item', []]], $controller->authChecks);
    }

    public function testTheResponseOfTheDeleteHookReplacesTheRedirectToTheList(): void
    {
        $request = $this->request(body: ['_token' => self::TOKEN]);
        $controller = $this->controller($request);
        $controller->additionalDeleteResponse = new Response('after delete');

        $response = $controller->deleteAction($request, $this->tokenProvider($request), $this->dispatcher(), $this->createStub(ParserContext::class));

        self::assertSame('after delete', $response->getContent());
    }

    public function testADeletionWithNoResponseFromTheHookGoesBackToTheList(): void
    {
        $request = $this->request(body: ['_token' => self::TOKEN]);

        $response = $this->controller($request)->deleteAction($request, $this->tokenProvider($request), $this->dispatcher(), $this->createStub(ParserContext::class));

        self::assertSame('list', $response->getContent());
    }

    public function testTheResponseOfThePositionHookReplacesTheRedirectToTheList(): void
    {
        $request = $this->request(body: ['mode' => 'up', '_token' => self::TOKEN]);
        $controller = $this->controller($request);
        $controller->additionalPositionResponse = new Response('after position');

        $response = $controller->updatePositionAction($request, $this->dispatcher());

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('after position', $response->getContent());
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, string>, int, int}>
     */
    public static function positionRequests(): iterable
    {
        yield 'up in the body' => [['mode' => 'up', 'position' => '4'], [], UpdatePositionEvent::POSITION_UP, 4];
        yield 'up in the query string' => [[], ['mode' => 'up', 'position' => '4'], UpdatePositionEvent::POSITION_UP, 4];
        yield 'down in the body' => [['mode' => 'down', 'position' => '2'], [], UpdatePositionEvent::POSITION_DOWN, 2];
        yield 'down in the query string' => [[], ['mode' => 'down', 'position' => '2'], UpdatePositionEvent::POSITION_DOWN, 2];
        yield 'an absolute position' => [['position' => '7'], [], UpdatePositionEvent::POSITION_ABSOLUTE, 7];
        yield 'a mode nobody knows is an absolute position' => [['mode' => 'sideways', 'position' => '7'], [], UpdatePositionEvent::POSITION_ABSOLUTE, 7];
        yield 'the body wins over the query string' => [['mode' => 'up', 'position' => '3'], ['mode' => 'down', 'position' => '9'], UpdatePositionEvent::POSITION_UP, 3];
    }

    /**
     * @param array<string, string> $body
     * @param array<string, string> $query
     */
    #[DataProvider('positionRequests')]
    public function testThePositionChangeReadsItsModeAndPositionFromTheRequest(array $body, array $query, int $expectedMode, int $expectedPosition): void
    {
        $request = $this->request(query: $query, body: $body + ['_token' => self::TOKEN]);
        $controller = $this->controller($request);

        $controller->updatePositionAction($request, $this->dispatcher());

        self::assertSame([[$expectedMode, $expectedPosition]], $controller->positionChanges);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);

        return $dispatcher;
    }

    private function tokenProvider(Request $request): TokenProvider
    {
        $stack = new RequestStack();
        $stack->push($request);

        return new TokenProvider($stack, $this->createStub(TranslatorInterface::class), self::SESSION_KEY);
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $body
     */
    private function request(array $query = [], array $body = []): Request
    {
        $request = new Request($query, $body);

        $session = new Session(new MockArraySessionStorage());
        $session->set(self::SESSION_KEY, self::TOKEN);
        $request->setSession($session);

        return $request;
    }

    private function controller(Request $request, ?string $moduleCode = null): CrudControllerDouble
    {
        $stack = new RequestStack();
        $stack->push($request);

        $controller = new CrudControllerDouble($moduleCode);
        $controller->requestStack = $stack;
        $controller->tokenProvider = $this->tokenProvider($request);

        return $controller;
    }
}

/**
 * A module controller as modules write them: it overrides the follow-up hooks and falls back
 * on the base class when it has nothing of its own to answer.
 */
final class CrudControllerDouble extends AbstractCrudController
{
    public ?Response $authResponse = null;
    public ?Response $additionalDeleteResponse = null;
    public ?Response $additionalPositionResponse = null;

    /** @var list<array{string, list<string>}> */
    public array $authChecks = [];

    /** @var list<array{int, int}> */
    public array $positionChanges = [];

    public function __construct(?string $moduleCode)
    {
        parent::__construct('item', null, null, 'admin.item', null, null, 'item.delete', 'item.toggle', 'item.position', $moduleCode);
    }

    protected function checkAuth(mixed $resources, mixed $modules, mixed $accesses): ?Response
    {
        $this->authChecks[] = [$resources, $modules];

        return $this->authResponse;
    }

    protected function errorPage(\Exception|string $message, int $status = 500): Response
    {
        return new Response($message instanceof \Exception ? $message->getMessage() : $message, $status);
    }

    protected function renderAfterDeleteError(ParserContext $parserContext, \Exception $e): Response
    {
        return new Response($e->getMessage());
    }

    protected function createUpdatePositionEvent(int $positionChangeMode, int $positionValue): ActionEvent
    {
        $this->positionChanges[] = [$positionChangeMode, $positionValue];

        return new class extends ActionEvent {};
    }

    protected function performAdditionalDeleteAction(ActionEvent|ActiveRecordEvent|null $deleteEvent): ?Response
    {
        return $this->additionalDeleteResponse ?? parent::performAdditionalDeleteAction($deleteEvent);
    }

    protected function performAdditionalUpdatePositionAction(ActionEvent $positionChangeEvent): ?Response
    {
        return $this->additionalPositionResponse ?? parent::performAdditionalUpdatePositionAction($positionChangeEvent);
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
}
