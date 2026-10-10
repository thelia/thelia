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
 * The express buttons a front asks for.
 *
 * The operation is open, like the list of payment methods next door, and it stays
 * harmless for the same reason: it reads the cart off the request and takes no cart in a
 * parameter, so there is nothing to enumerate. A shop that has enabled no zone answers
 * with an empty collection rather than an error, which is what every shop does until a
 * merchant says otherwise.
 */
final class ExpressPaymentButtonApiTest extends ApiTestCase
{
    public function testTheCollectionAnswersWithoutAnAccount(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/payment/express-buttons');

        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('hydra:member', $data);
        self::assertSame([], $data['hydra:member']);
    }

    public function testTheCheckoutZoneIsAskedForByName(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/payment/express-buttons?zone=checkout');

        self::assertJsonResponseSuccessful($response);
    }

    /**
     * A zone this version does not know, such as the cart a former version offered, is not
     * an error to report to a buyer: the page simply has no button to show.
     */
    public function testAnUnknownZoneAnswersWithNothing(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/payment/express-buttons?zone=cart');

        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);

        self::assertSame([], $data['hydra:member']);
    }
}
