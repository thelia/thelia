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

namespace Thelia\Tests\Api;

use Thelia\Test\ApiTestCase;

/**
 * The API is stateless: no request under /api is given a session, whichever way the
 * path is spelled. The router decodes the path before matching, so /%61pi/front/... is
 * the same endpoint as /api/front/... and must be treated the same.
 */
final class ApiStatelessnessTest extends ApiTestCase
{
    public function testNoSessionIsOpenedForAnApiRequest(): void
    {
        $this->client->getCookieJar()->clear();
        $response = $this->jsonRequest('GET', '/api/front/products');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([], $response->headers->getCookies(), 'An API request must not open a session.');
    }

    public function testNoSessionIsOpenedForAnEncodedSpellingOfAnApiRequest(): void
    {
        $this->client->getCookieJar()->clear();
        $response = $this->jsonRequest('GET', '/%61pi/front/products');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([], $response->headers->getCookies(), 'The spelling of the path must not decide whether a session is opened.');
    }
}
