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

namespace Thelia\Tests\Api\Webhook;

use Thelia\Domain\Webhook\WebhookPayloadBuilder;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Test\ApiTestCase;

/**
 * The body of a webhook is the order as the admin API shows it. A payload built from the
 * serialization has to be checked against the real response, on a cache that was cleared
 * and warmed: a warm-up once removed the extension attributes of the API responses, and
 * the payload lost fields without any error.
 */
final class WebhookPayloadMatchesAdminApiTest extends ApiTestCase
{
    public function testTheOrderPayloadCarriesTheSameFieldsAsTheAdminApiResponse(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->customer($factory->customerTitle()));

        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId(), token: $this->authenticateAsAdmin(), format: 'json');
        self::assertJsonResponseSuccessful($response);
        $api = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $payload = static::getContainer()->get(WebhookPayloadBuilder::class)->data('order.created', $order, WebhookStatus::PAYLOAD_FULL);

        $lost = array_values(array_diff(array_keys($api), array_keys($payload)));
        $gained = array_filter(array_diff_key($payload, $api), static fn (mixed $value): bool => null !== $value);

        self::assertNotEmpty($api);
        self::assertSame([], $lost, 'The payload lost fields compared with the admin API.');
        self::assertSame([], $gained, 'The payload carries values the admin API does not show. The API only leaves out what is null.');
        self::assertSame($api['ref'], $payload['ref']);
        self::assertSame($api['id'], $payload['id']);
    }
}
