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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\EventListener\ResponseHeadersListener;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Test\IntegrationTestCase;

final class ResponseHeadersListenerTest extends IntegrationTestCase
{
    public function testAMainResponseCarriesTheDefaultHeaders(): void
    {
        $response = new Response();

        (new ResponseHeadersListener())->addDefaultHeaders($this->responseEvent($response, HttpKernelInterface::MAIN_REQUEST));

        foreach (ResponseHeadersListener::DEFAULT_HEADERS as $name => $value) {
            self::assertSame($value, $response->headers->get($name), $name);
        }
    }

    public function testAHeaderAlreadySetIsLeftAlone(): void
    {
        $response = new Response();
        $response->headers->set('X-Frame-Options', 'DENY');

        (new ResponseHeadersListener())->addDefaultHeaders($this->responseEvent($response, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testASubRequestIsLeftAlone(): void
    {
        $response = new Response();

        (new ResponseHeadersListener())->addDefaultHeaders($this->responseEvent($response, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($response->headers->has('X-Frame-Options'));
    }

    private function responseEvent(Response $response, int $requestType): ResponseEvent
    {
        return new ResponseEvent(self::$kernel, TheliaRequest::create('/'), $requestType, $response);
    }
}
