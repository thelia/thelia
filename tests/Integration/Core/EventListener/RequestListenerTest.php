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

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\EventListener\RequestListener;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpFoundation\Session\SessionManager;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CurrencyQuery;
use Thelia\Test\IntegrationTestCase;

final class RequestListenerTest extends IntegrationTestCase
{
    private RequestListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = new RequestListener(
            Translator::getInstance(),
            $this->getService(EventDispatcherInterface::class),
            $this->getService(SessionManager::class),
        );
    }

    private function createRequestEvent(TheliaRequest $request): RequestEvent
    {
        return new RequestEvent(
            self::$kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    public function testJsonBodyParsesJsonPostIntoRequestBag(): void
    {
        $request = TheliaRequest::create(
            '/api/test',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Widget', 'price' => 42]),
        );

        $event = $this->createRequestEvent($request);
        $this->listener->jsonBody($event);

        self::assertSame('Widget', $request->request->get('name'));
        self::assertSame(42, $request->request->get('price'));
    }

    public function testJsonBodyIgnoresGetRequests(): void
    {
        $request = TheliaRequest::create(
            '/api/test',
            'GET',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'ignored']),
        );

        $event = $this->createRequestEvent($request);
        $this->listener->jsonBody($event);

        self::assertNull($request->request->get('name'));
    }

    public function testJsonBodyIgnoresEmptyContent(): void
    {
        $request = TheliaRequest::create(
            '/api/test',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '',
        );

        $event = $this->createRequestEvent($request);
        $this->listener->jsonBody($event);

        self::assertEmpty($request->request->all());
    }

    public function testJsonBodyHandlesPatchAndDelete(): void
    {
        foreach (['PATCH', 'DELETE'] as $method) {
            $request = TheliaRequest::create(
                '/api/test',
                $method,
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['action' => 'test_'.$method]),
            );

            $event = $this->createRequestEvent($request);
            $this->listener->jsonBody($event);

            self::assertSame('test_'.$method, $request->request->get('action'));
        }
    }

    public function testCheckCurrencySetsSessionCurrencyFromQueryParam(): void
    {
        $currency = CurrencyQuery::create()
            ->filterByVisible(true)
            ->findOne();

        if (null === $currency) {
            $currency = $this->createFixtureFactory()->currency();
        }

        $request = TheliaRequest::create(
            '/?currency='.$currency->getCode(),
        );

        // Attach a session to the request so the listener can use it.
        $session = new Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
        $request->setSession($session);

        $event = $this->createRequestEvent($request);
        $this->listener->checkCurrency($event);

        self::assertSame($currency->getCode(), $session->getCurrency()->getCode());
    }

    public function testCheckCurrencyFallsBackToDefaultForInvalidCode(): void
    {
        $request = TheliaRequest::create(
            '/?currency=INVALID_CURRENCY_CODE',
        );

        $session = new Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
        $request->setSession($session);

        $event = $this->createRequestEvent($request);
        $this->listener->checkCurrency($event);

        // Should fall back to default currency.
        $defaultCurrency = \Thelia\Model\Currency::getDefaultCurrency();
        self::assertSame($defaultCurrency->getCode(), $session->getCurrency()->getCode());
    }
}
