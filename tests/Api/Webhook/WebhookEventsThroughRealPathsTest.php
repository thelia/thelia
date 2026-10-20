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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Service\CustomerRegistrationService;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Model\WebhookSubscription;
use Thelia\Model\WebhookSubscriptionQuery;
use Thelia\Test\ApiTestCase;

/**
 * The events of a webhook must come out of the paths a client really takes, not only out
 * of the listener called by hand: the API saves the model without the actions, and the
 * storefront registers through its own event.
 */
final class WebhookEventsThroughRealPathsTest extends ApiTestCase
{
    /** @var list<array{url: string, body: array<string, mixed>}> */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requests = [];
        WebhookSubscriptionQuery::create()->deleteAll();
        static::getContainer()->set('thelia.webhook.http_client', new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['url' => $url, 'body' => json_decode((string) $options['body'], true, 512, \JSON_THROW_ON_ERROR)];

            return new MockResponse('ok', ['http_code' => 200]);
        }));
        (new WebhookSubscription())
            ->setTitle('Real paths')
            ->setUrl('https://hooks.example.com/in')
            ->setSecret(bin2hex(random_bytes(32)))
            ->setEvents('["customer.created","product.updated"]')
            ->setPayloadMode(WebhookStatus::PAYLOAD_FULL)
            ->setStatus(WebhookStatus::SUBSCRIPTION_ACTIVE)
            ->save();
    }

    public function testACustomerCreatedThroughTheAdminApiIsPublished(): void
    {
        $title = $this->createFixtureFactory()->customerTitle();

        $response = $this->jsonRequest('POST', '/api/admin/customers', [
            'customerTitle' => '/api/admin/customer_titles/'.$title->getId(),
            'firstname' => 'Api',
            'lastname' => 'Created',
            'email' => 'webhook-api-created@example.com',
            'password' => 'Str0ng-passw0rd!',
        ], $this->authenticateAsAdmin());

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['customer.created'], array_column(array_column($this->requests, 'body'), 'type'));
        self::assertSame('webhook-api-created@example.com', $this->requests[0]['body']['data']['email']);
    }

    public function testAnUpdateOfACustomerThroughTheApiPublishesNothing(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        $response = $this->jsonRequest('PATCH', '/api/admin/customers/'.$customer->getId(), ['firstname' => 'Renamed'], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->requests);
    }

    public function testACustomerRegisteredFromTheStorefrontIsPublished(): void
    {
        $title = $this->createFixtureFactory()->customerTitle();

        $customer = static::getContainer()->get(CustomerRegistrationService::class)->registerCustomer(new CustomerRegisterDTO(
            firstname: 'Store',
            lastname: 'Front',
            email: 'webhook-storefront@example.com',
            password: 'Str0ng-passw0rd!',
            title: $title->getId(),
        ));

        self::assertSame(['customer.created'], array_column(array_column($this->requests, 'body'), 'type'));
        self::assertSame($customer->getId(), $this->requests[0]['body']['data']['id']);
    }

    public function testAProductWrittenThroughTheAdminApiIsPublished(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), ['visible' => false, 'i18ns' => ['en_US' => ['title' => 'Written by the API', 'locale' => 'en_US']]], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['product.updated'], array_column(array_column($this->requests, 'body'), 'type'));
        self::assertSame($product->getId(), $this->requests[0]['body']['data']['id']);
    }
}
