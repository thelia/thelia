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

namespace Thelia\Tests\Api\Front;

use Thelia\Test\ApiTestCase;

/**
 * A front client has to name the customer title of an address or of a
 * registration, and it can only do that with an IRI the front API answers to.
 * The collection alone left the title reachable through the admin surface only.
 */
final class CustomerTitleApiTest extends ApiTestCase
{
    public function testACustomerTitleIsReadableOnItsOwn(): void
    {
        $title = $this->createFixtureFactory()->customerTitle();

        $response = $this->jsonRequest('GET', '/api/front/customer_titles/'.$title->getId());

        self::assertJsonResponseSuccessful($response);
        self::assertSame($title->getId(), json_decode((string) $response->getContent(), true)['id']);
    }
}
